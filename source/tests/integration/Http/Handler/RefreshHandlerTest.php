<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Auth\AccessToken;
use Pidgey\Auth\AccessTokenClaims;
use Pidgey\Auth\AccessTokenCookie;
use Pidgey\Auth\Base64Url;
use Pidgey\Auth\RefreshTokenValue;
use Pidgey\Auth\RotationLost;
use Pidgey\Auth\SecretKeys;
use Pidgey\Auth\SessionResponse;
use Pidgey\Auth\SessionRotation;
use Pidgey\Auth\Sessions;
use Pidgey\Auth\SessionTokens;
use Pidgey\Auth\SessionUuid;
use Pidgey\Auth\UserPayload;
use Pidgey\Container;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\FieldRules;
use Pidgey\Http\FrontController;
use Pidgey\Http\Handler\RefreshHandler;
use Pidgey\Http\HandlerFactory;
use Pidgey\Http\HttpException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use Pidgey\Http\Route;
use Pidgey\Http\RouteMatch;
use Pidgey\Http\Router;
use Pidgey\Http\SetCookie;
use Pidgey\Http\UnauthorizedException;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Http\Validator;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Infra\SystemWallClock;
use Pidgey\Infra\Transaction;
use Pidgey\Model\RefreshToken;
use Pidgey\Model\RevokedReason;
use Pidgey\Model\Row;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\RefreshTokenRepository;
use Pidgey\Repository\Sql;
use Pidgey\Repository\UserRepository;
use Pidgey\Tests\Support\SessionEndpointTestCase;

#[CoversClass(RefreshHandler::class)]
#[UsesClass(AccessToken::class)]
#[UsesClass(AccessTokenClaims::class)]
#[UsesClass(AccessTokenCookie::class)]
#[UsesClass(Base64Url::class)]
#[UsesClass(Container::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(ErrorFormatter::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(FieldRules::class)]
#[UsesClass(FrontController::class)]
#[UsesClass(HandlerFactory::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(ProcessingRequest::class)]
#[UsesClass(RefreshToken::class)]
#[UsesClass(RefreshTokenRepository::class)]
#[UsesClass(RefreshTokenValue::class)]
#[UsesClass(Request::class)]
#[UsesClass(RequestHandler::class)]
#[UsesClass(Response::class)]
#[UsesClass(RevokedReason::class)]
#[UsesClass(RotationLost::class)]
#[UsesClass(Route::class)]
#[UsesClass(RouteMatch::class)]
#[UsesClass(Router::class)]
#[UsesClass(Row::class)]
#[UsesClass(SecretKeys::class)]
#[UsesClass(SessionResponse::class)]
#[UsesClass(SessionRotation::class)]
#[UsesClass(Sessions::class)]
#[UsesClass(SessionTokens::class)]
#[UsesClass(SessionUuid::class)]
#[UsesClass(SetCookie::class)]
#[UsesClass(Sql::class)]
#[UsesClass(SystemWallClock::class)]
#[UsesClass(Transaction::class)]
#[UsesClass(UnauthorizedException::class)]
#[UsesClass(User::class)]
#[UsesClass(UserPayload::class)]
#[UsesClass(UserRepository::class)]
#[UsesClass(UserStatus::class)]
#[UsesClass(ValidationFailedException::class)]
#[UsesClass(Validator::class)]
final class RefreshHandlerTest extends SessionEndpointTestCase
{
    private const PATH = '/auth/refresh.json';

    private const UNAUTHORIZED = ['code' => 'UNAUTHORIZED', 'message' => 'Invalid or expired refresh token'];

    /**
     * @return array<string, array{UserStatus}>
     */
    public static function liveStatuses(): array
    {
        return [
            'approved' => [UserStatus::Approved],
            'pending' => [UserStatus::Pending],
        ];
    }

    #[DataProvider('liveStatuses')]
    public function testALiveUserGetsANewTokenAndCookieForTheSameSession(UserStatus $status): void
    {
        $user = $this->account($status, isAdmin: true);
        $session = $this->startSession($user);

        $response = $this->refresh($session->refreshToken());
        $body = self::json($response);
        $cookies = self::cookies($response);

        $this->assertSame(201, $response->status());
        $this->assertSame(['user', 'refreshToken'], array_keys($body));
        $this->assertSame(UserPayload::fromUser($user), $body['user']);
        $this->assertNotSame($session->refreshToken(), $body['refreshToken']);
        $this->assertNull($this->tokenRow($body['refreshToken'])['revoked_at']);
        $this->assertSame($session->sessionUuid, $this->tokenRow($body['refreshToken'])['session_uuid']);
        $this->assertSame('rotated', $this->tokenRow($session->refreshToken())['revoked_reason']);
        $this->assertCount(1, $cookies);
        $this->assertMatchesRegularExpression(
            '/\ASet-Cookie: access_token=[^;]+; HttpOnly; Secure; SameSite=Strict; Path=\/; Max-Age='
            . self::ACCESS_TOKEN_TTL . '\z/',
            $cookies[0],
        );
        preg_match('/access_token=([^;]+);/', $cookies[0], $match);
        $claims = (new AccessToken(new SecretKeys(self::SECRET_KEY), self::ACCESS_TOKEN_TTL, new SystemWallClock()))
            ->verify($match[1]);
        $this->assertSame($user->id, $claims?->userId);
        $this->assertSame($session->sessionUuid, $claims?->sessionId);
    }

    public function testAnUnknownTokenIsUnauthorized(): void
    {
        $this->startSession($this->account());

        $this->assertUnauthorized($this->refresh('unknown-' . bin2hex(random_bytes(16))));
    }

    public function testTheTokenIsNotTrimmed(): void
    {
        $session = $this->startSession($this->account());

        $this->assertUnauthorized($this->refresh(' ' . $session->refreshToken() . ' '));
        $this->assertNull($this->tokenRow($session->refreshToken())['revoked_at']);
    }

    public function testAnExpiredTokenIsUnauthorized(): void
    {
        $user = $this->account();
        $this->storeToken($user->id, 'expired-token', expiresAt: self::utc('-1 minute'));

        $this->assertUnauthorized($this->refresh('expired-token'));
        $this->assertNull($this->tokenRow('expired-token')['revoked_at']);
    }

    public function testReplayingARotatedTokenRevokesEverySessionOfTheUser(): void
    {
        $user = $this->account();
        $session = $this->startSession($user);
        $other = $this->startSession($user);
        $rotated = self::json($this->refresh($session->refreshToken()))['refreshToken'];

        $this->assertSameAsAnUnknownToken($this->refresh($session->refreshToken()));
        $this->assertSame('replay_detected', $this->tokenRow($rotated)['revoked_reason']);
        $this->assertSame('replay_detected', $this->tokenRow($other->refreshToken())['revoked_reason']);
        $this->assertSame('rotated', $this->tokenRow($session->refreshToken())['revoked_reason']);
    }

    public function testATokenRevokedForAnotherReasonIsUnauthorizedWithoutReplayDetection(): void
    {
        $user = $this->account();
        $other = $this->startSession($user);
        $this->storeToken($user->id, 'logged-out', revokedReason: 'logout');

        $this->assertUnauthorized($this->refresh('logged-out'));
        $this->assertSame('logout', $this->tokenRow('logged-out')['revoked_reason']);
        $this->assertNull($this->tokenRow($other->refreshToken())['revoked_at']);
    }

    public function testAMissingUserIsUnauthorized(): void
    {
        $user = $this->account();
        $session = $this->startSession($user);
        $this->deleteUser($user->id);

        $this->assertUnauthorized($this->refresh($session->refreshToken()));
        $this->assertNull($this->tokenRow($session->refreshToken())['revoked_at']);
    }

    public function testADeniedUserIsUnauthorizedAndOnlyThePresentedTokenIsRevoked(): void
    {
        $user = $this->account(UserStatus::Denied);
        $session = $this->startSession($user);
        $other = $this->startSession($user);

        $this->assertSameAsAnUnknownToken($this->refresh($session->refreshToken()));
        $this->assertSame('admin_denied', $this->tokenRow($session->refreshToken())['revoked_reason']);
        $this->assertNull($this->tokenRow($other->refreshToken())['revoked_at']);
    }

    /**
     * @return array<string, array{array<string, mixed>|string, list<string>}>
     */
    public static function invalidBodies(): array
    {
        return [
            'nothing' => ['', ['refreshToken should not be empty']],
            'missing field' => ['{}', ['refreshToken should not be empty']],
            'empty string' => [['refreshToken' => ''], ['refreshToken should not be empty']],
            'not a string' => [['refreshToken' => 42], ['refreshToken must be a string']],
        ];
    }

    /**
     * @param array<string, mixed>|string $body
     * @param list<string>                $details
     */
    #[DataProvider('invalidBodies')]
    public function testInvalidBodiesAnswer400(array|string $body, array $details): void
    {
        $response = $this->send($body);

        $this->assertSame(400, $response->status());
        $this->assertSame('VALIDATION_FAILED', self::json($response)['error']['code']);
        $this->assertSame($details, self::json($response)['error']['details']);
        $this->assertSame([], self::cookies($response));
    }

    private function assertUnauthorized(Response $response): void
    {
        $this->assertSame(401, $response->status());
        $this->assertSame(self::UNAUTHORIZED, self::json($response)['error']);
        $this->assertSame([], self::cookies($response));
    }

    /**
     * Asserts the response is indistinguishable from an unknown token's: same status, headers and
     * body (only the `timestamp` value may differ), so it never reveals which check failed.
     */
    private function assertSameAsAnUnknownToken(Response $response): void
    {
        $unknown = $this->refresh('unknown-' . bin2hex(random_bytes(16)));
        $expected = self::json($unknown);
        $actual = self::json($response);

        $this->assertUnauthorized($response);
        $this->assertSame($unknown->status(), $response->status());
        $this->assertSame($unknown->headers(), $response->headers());
        $this->assertSame(array_keys($expected), array_keys($actual));
        unset($expected['timestamp'], $actual['timestamp']);
        $this->assertSame($expected, $actual);
    }

    private function deleteUser(int $id): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $this->pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function refresh(string $plain): Response
    {
        return $this->send(['refreshToken' => $plain]);
    }

    /**
     * @param array<string, mixed>|string $body
     */
    private function send(array|string $body): Response
    {
        $router = new Router();
        $router->post(self::PATH, RefreshHandler::class);

        return $this->post($router, self::PATH, $body);
    }
}
