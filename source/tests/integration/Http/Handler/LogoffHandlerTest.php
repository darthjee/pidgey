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
use Pidgey\Http\Cors;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\FieldRules;
use Pidgey\Http\FrontController;
use Pidgey\Http\Handler\LogoffHandler;
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
use Pidgey\Http\TrustedOrigins;
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

#[CoversClass(LogoffHandler::class)]
#[UsesClass(AccessToken::class)]
#[UsesClass(AccessTokenClaims::class)]
#[UsesClass(AccessTokenCookie::class)]
#[UsesClass(Base64Url::class)]
#[UsesClass(Container::class)]
#[UsesClass(Cors::class)]
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
#[UsesClass(TrustedOrigins::class)]
#[UsesClass(UnauthorizedException::class)]
#[UsesClass(User::class)]
#[UsesClass(UserPayload::class)]
#[UsesClass(UserRepository::class)]
#[UsesClass(UserStatus::class)]
#[UsesClass(ValidationFailedException::class)]
#[UsesClass(Validator::class)]
final class LogoffHandlerTest extends SessionEndpointTestCase
{
    private const PATH = '/auth/logoff.json';

    /**
     * @return array<string, array{UserStatus}>
     */
    public static function everyStatus(): array
    {
        return [
            'approved' => [UserStatus::Approved],
            'pending' => [UserStatus::Pending],
            'denied' => [UserStatus::Denied],
        ];
    }

    #[DataProvider('everyStatus')]
    public function testAnActiveTokenEndsItsSessionOnly(UserStatus $status): void
    {
        $user = $this->account($status);
        $session = $this->startSession($user);
        $other = $this->startSession($user);
        $sibling = 'sibling-' . bin2hex(random_bytes(8));
        $this->storeToken($user->id, $sibling, sessionUuid: $session->sessionUuid);

        $response = $this->logoff($session->refreshToken());

        $this->assertCleared($response);
        $this->assertSame('logout', $this->tokenRow($session->refreshToken())['revoked_reason']);
        $this->assertSame('logout', $this->tokenRow($sibling)['revoked_reason']);
        $this->assertNull($this->tokenRow($other->refreshToken())['revoked_at']);
    }

    public function testARotatedTokenStillEndsItsSession(): void
    {
        $user = $this->account();
        $session = $this->startSession($user);
        $current = $this->container()->sessions()->refresh($session->refreshToken())?->refreshToken()
            ?? self::fail('The refresh failed.');

        $this->assertCleared($this->logoff($session->refreshToken()));
        $this->assertSame('logout', $this->tokenRow($current)['revoked_reason']);
        $this->assertSame('rotated', $this->tokenRow($session->refreshToken())['revoked_reason']);
    }

    public function testAnExpiredTokenStillEndsItsSession(): void
    {
        $user = $this->account();
        $session = $this->startSession($user);
        $this->storeToken($user->id, 'expired-token', $session->sessionUuid, self::utc('-1 minute'));

        $this->assertCleared($this->logoff('expired-token'));
        $this->assertSame('logout', $this->tokenRow($session->refreshToken())['revoked_reason']);
    }

    public function testAnExpiredTokenWithNoActiveRowStillAnswers204(): void
    {
        $user = $this->account();
        $this->storeToken($user->id, 'expired-token', expiresAt: self::utc('-1 minute'));

        $this->assertCleared($this->logoff('expired-token'));
    }

    public function testAnAlreadyRevokedRowKeepsItsReasonAndTime(): void
    {
        $user = $this->account();
        $session = $this->startSession($user);
        $this->storeToken($user->id, 'revoked-token', $session->sessionUuid, revokedReason: 'admin_edit');
        $before = $this->tokenRow('revoked-token');

        $this->assertCleared($this->logoff('revoked-token'));
        $this->assertSame($before, $this->tokenRow('revoked-token'));
        $this->assertSame('logout', $this->tokenRow($session->refreshToken())['revoked_reason']);
    }

    public function testAnUnknownTokenIsASilentNoOp(): void
    {
        $user = $this->account();
        $session = $this->startSession($user);
        $before = $this->tokenRow($session->refreshToken());

        $this->assertCleared($this->logoff('unknown-' . bin2hex(random_bytes(16))));
        $this->assertSame($before, $this->tokenRow($session->refreshToken()));
        $this->assertSame(1, $this->rowCount('refresh_tokens'));
    }

    public function testTheTokenIsNotTrimmed(): void
    {
        $session = $this->startSession($this->account());

        $this->assertCleared($this->logoff(' ' . $session->refreshToken() . ' '));
        $this->assertNull($this->tokenRow($session->refreshToken())['revoked_at']);
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
    public function testInvalidBodiesAnswer400WithoutClearingTheCookie(array|string $body, array $details): void
    {
        $session = $this->startSession($this->account());

        $response = $this->send($body);

        $this->assertSame(400, $response->status());
        $this->assertSame('VALIDATION_FAILED', self::json($response)['error']['code']);
        $this->assertSame($details, self::json($response)['error']['details']);
        $this->assertSame([], self::cookies($response));
        $this->assertNull($this->tokenRow($session->refreshToken())['revoked_at']);
    }

    private function assertCleared(Response $response): void
    {
        $this->assertSame(204, $response->status());
        $this->assertSame('', $response->body());
        $this->assertSame([
            'Set-Cookie: access_token=; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=0; '
            . 'Expires=Thu, 01 Jan 1970 00:00:00 GMT',
            'Vary: Origin',
        ], $response->headers());
    }

    private function logoff(string $plain): Response
    {
        return $this->send(['refreshToken' => $plain]);
    }

    /**
     * @param array<string, mixed>|string $body
     */
    private function send(array|string $body): Response
    {
        $router = new Router();
        $router->delete(self::PATH, LogoffHandler::class);

        return $this->delete($router, self::PATH, $body);
    }
}
