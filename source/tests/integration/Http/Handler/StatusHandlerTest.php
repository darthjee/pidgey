<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http\Handler;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Auth\AccessToken;
use Pidgey\Auth\AccessTokenClaims;
use Pidgey\Auth\ActiveSession;
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
use Pidgey\Http\Handler\StatusHandler;
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

#[CoversClass(StatusHandler::class)]
#[UsesClass(AccessToken::class)]
#[UsesClass(AccessTokenClaims::class)]
#[UsesClass(ActiveSession::class)]
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
final class StatusHandlerTest extends SessionEndpointTestCase
{
    private const PATH = '/auth/status.json';

    private const ANONYMOUS = '{"loggedIn":false,"isAdmin":false}';

    /**
     * @return array<string, array{bool}>
     */
    public static function adminFlags(): array
    {
        return ['admin' => [true], 'not admin' => [false]];
    }

    #[DataProvider('adminFlags')]
    public function testAnApprovedUserIsLoggedIn(bool $isAdmin): void
    {
        $session = $this->startSession($this->account(isAdmin: $isAdmin));
        $before = $this->tokenRow($session->refreshToken());

        $response = $this->check($session->refreshToken());

        $this->assertSame(201, $response->status());
        $this->assertSame(['loggedIn' => true, 'isAdmin' => $isAdmin], self::json($response));
        $this->assertSame([], self::cookies($response));
        $this->assertSame($before, $this->tokenRow($session->refreshToken()));
    }

    public function testIsAdminIsReadFromTheDatabase(): void
    {
        $user = $this->account();
        $session = $this->startSession($user);
        $this->pdo->prepare('UPDATE users SET is_admin = 1 WHERE id = :id')->execute(['id' => $user->id]);

        $this->assertSame(
            ['loggedIn' => true, 'isAdmin' => true],
            self::json($this->check($session->refreshToken())),
        );
    }

    public function testAPendingUserKeepsTheSession(): void
    {
        $session = $this->startSession($this->account(UserStatus::Pending, isAdmin: true));
        $before = $this->tokenRow($session->refreshToken());

        $response = $this->check($session->refreshToken());

        $this->assertSame(201, $response->status());
        $this->assertSame(['loggedIn' => false, 'isAdmin' => false, 'status' => 'pending'], self::json($response));
        $this->assertSame([], self::cookies($response));
        $this->assertSame($before, $this->tokenRow($session->refreshToken()));
    }

    public function testADeniedUserGetsTheAnonymousAnswerAndThePresentedTokenIsRevoked(): void
    {
        $user = $this->account(UserStatus::Denied, isAdmin: true);
        $session = $this->startSession($user);
        $other = $this->startSession($user);

        $denied = $this->check($session->refreshToken());
        $unknown = $this->check('unknown-' . bin2hex(random_bytes(16)));

        $this->assertSame(201, $denied->status());
        $this->assertSame(self::ANONYMOUS, $denied->body());
        $this->assertSame($unknown->body(), $denied->body());
        $this->assertSame($unknown->status(), $denied->status());
        $this->assertSame($unknown->headers(), $denied->headers());
        $this->assertSame('admin_denied', $this->tokenRow($session->refreshToken())['revoked_reason']);
        $this->assertNull($this->tokenRow($other->refreshToken())['revoked_at']);
    }

    public function testAnUnknownTokenIsAnonymous(): void
    {
        $session = $this->startSession($this->account());
        $before = $this->tokenRow($session->refreshToken());

        $this->assertAnonymous($this->check('unknown-' . bin2hex(random_bytes(16))));
        $this->assertSame($before, $this->tokenRow($session->refreshToken()));
    }

    public function testARotatedTokenIsAnonymousWithoutReplayDetection(): void
    {
        $session = $this->startSession($this->account());
        $current = $this->container()->sessions()->refresh($session->refreshToken())?->refreshToken()
            ?? self::fail('The refresh failed.');
        $before = $this->rows();

        $this->assertAnonymous($this->check($session->refreshToken()));
        $this->assertSame($before, $this->rows());
        $this->assertNull($this->tokenRow($current)['revoked_at']);
    }

    /**
     * @return array<string, array{string|null, string|null}>
     */
    public static function inactiveTokens(): array
    {
        return [
            'revoked by logout' => [null, 'logout'],
            'revoked by an admin edit' => [null, 'admin_edit'],
            'expired' => ['-1 minute', null],
        ];
    }

    #[DataProvider('inactiveTokens')]
    public function testAnInactiveTokenIsAnonymousWithoutSideEffect(?string $expiresAt, ?string $reason): void
    {
        $user = $this->account();
        $this->storeToken(
            $user->id,
            'inactive-token',
            expiresAt: $expiresAt === null ? null : self::utc($expiresAt),
            revokedReason: $reason,
        );
        $before = $this->rows();

        $this->assertAnonymous($this->check('inactive-token'));
        $this->assertSame($before, $this->rows());
    }

    public function testAMissingUserIsAnonymous(): void
    {
        $user = $this->account();
        $session = $this->startSession($user);
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $this->pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $user->id]);
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->assertAnonymous($this->check($session->refreshToken()));
        $this->assertNull($this->tokenRow($session->refreshToken())['revoked_at']);
    }

    public function testStatusIsRepeatableAndNeverRotates(): void
    {
        $session = $this->startSession($this->account());

        $first = $this->check($session->refreshToken());
        $second = $this->check($session->refreshToken());

        $this->assertSame($first->body(), $second->body());
        $this->assertSame(1, $this->rowCount('refresh_tokens'));
        $this->assertNotNull($this->container()->sessions()->refresh($session->refreshToken()));
    }

    public function testTheTokenIsNotTrimmed(): void
    {
        $session = $this->startSession($this->account());

        $this->assertAnonymous($this->check(' ' . $session->refreshToken() . ' '));
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

    private function assertAnonymous(Response $response): void
    {
        $this->assertSame(201, $response->status());
        $this->assertSame(self::ANONYMOUS, $response->body());
        $this->assertSame([], self::cookies($response));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        return $this->pdo->query('SELECT * FROM refresh_tokens ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }

    private function check(string $plain): Response
    {
        return $this->send(['refreshToken' => $plain]);
    }

    /**
     * @param array<string, mixed>|string $body
     */
    private function send(array|string $body): Response
    {
        $router = new Router();
        $router->post(self::PATH, StatusHandler::class);

        return $this->post($router, self::PATH, $body);
    }
}
