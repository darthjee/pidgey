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
use Pidgey\Auth\Passwords;
use Pidgey\Auth\RefreshTokenValue;
use Pidgey\Auth\Login;
use Pidgey\Auth\SecretKeys;
use Pidgey\Auth\SessionResponse;
use Pidgey\Auth\SessionRotation;
use Pidgey\Auth\Sessions;
use Pidgey\Auth\SessionTokens;
use Pidgey\Auth\SessionUuid;
use Pidgey\Auth\UserPayload;
use Pidgey\Container;
use Pidgey\Http\ForbiddenException;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\FieldRules;
use Pidgey\Http\FrontController;
use Pidgey\Http\Handler\LoginHandler;
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
use Pidgey\Model\Row;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\RefreshTokenRepository;
use Pidgey\Repository\Sql;
use Pidgey\Repository\UserRepository;
use Pidgey\Tests\Support\SessionEndpointTestCase;

#[CoversClass(LoginHandler::class)]
#[UsesClass(AccessToken::class)]
#[UsesClass(AccessTokenClaims::class)]
#[UsesClass(AccessTokenCookie::class)]
#[UsesClass(Base64Url::class)]
#[UsesClass(ForbiddenException::class)]
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
#[UsesClass(Passwords::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(ProcessingRequest::class)]
#[UsesClass(RefreshTokenRepository::class)]
#[UsesClass(RefreshTokenValue::class)]
#[UsesClass(Login::class)]
#[UsesClass(Request::class)]
#[UsesClass(RequestHandler::class)]
#[UsesClass(Response::class)]
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
#[UsesClass(User::class)]
#[UsesClass(UserPayload::class)]
#[UsesClass(UserRepository::class)]
#[UsesClass(UnauthorizedException::class)]
#[UsesClass(UserStatus::class)]
#[UsesClass(ValidationFailedException::class)]
#[UsesClass(Validator::class)]
final class LoginHandlerTest extends SessionEndpointTestCase
{
    private const PATH = '/auth/login.json';

    private const PASSWORD = 's3cretpass';

    private static string $digest;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$digest = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function sessionStatuses(): array
    {
        return [
            'approved' => ['approved'],
            'pending' => ['pending'],
        ];
    }

    #[DataProvider('sessionStatuses')]
    public function testSuccessAnswers201WithTheUserPayloadTheRefreshTokenAndTheCookie(string $status): void
    {
        $id = $this->user(status: $status, isAdmin: true);

        $response = $this->send(['username' => ' bob ', 'password' => self::PASSWORD]);
        $body = self::json($response);
        $cookies = self::cookies($response);

        $this->assertSame(201, $response->status());
        $this->assertSame(['user', 'refreshToken'], array_keys($body));
        $this->assertSame(
            ['id' => $id, 'username' => 'bob', 'email' => 'bob@example.com', 'isAdmin' => true],
            $body['user'],
        );
        $this->assertSame(
            RefreshTokenValue::hash($body['refreshToken']),
            $this->row('refresh_tokens', 'user_id', $id)['token_hash'],
        );
        $this->assertCount(1, $cookies);
        $this->assertMatchesRegularExpression(
            '/\ASet-Cookie: access_token=[^;]+; HttpOnly; Secure; SameSite=Strict; Path=\/; Max-Age='
            . self::ACCESS_TOKEN_TTL . '\z/',
            $cookies[0],
        );
        preg_match('/access_token=([^;]+);/', $cookies[0], $match);
        $claims = (new AccessToken(new SecretKeys(self::SECRET_KEY), self::ACCESS_TOKEN_TTL, new SystemWallClock()))
            ->verify($match[1]);
        $this->assertSame($id, $claims?->userId);
        $this->assertStringNotContainsString($status, $response->body());
    }

    public function testAnUnknownUsernameAndAWrongPasswordGiveTheSameAnswer(): void
    {
        $this->user();

        $unknown = $this->send(['username' => 'alice', 'password' => self::PASSWORD]);
        $wrong = $this->send(['username' => 'bob', 'password' => 'wrong-password']);

        $this->assertSame(401, $unknown->status());
        $this->assertSame(
            ['code' => 'UNAUTHORIZED', 'message' => 'Invalid username or password'],
            self::json($unknown)['error'],
        );
        $this->assertSame(self::json($unknown)['error'], self::json($wrong)['error']);
        $this->assertSame($unknown->status(), $wrong->status());
        $this->assertSame([], self::cookies($unknown));
        $this->assertSame([], self::cookies($wrong));
    }

    public function testAWrongPasswordOfADeniedUserGivesTheSame401(): void
    {
        $this->user(status: 'denied');

        $response = $this->send(['username' => 'bob', 'password' => 'wrong-password']);

        $this->assertSame(401, $response->status());
        $this->assertSame(
            ['code' => 'UNAUTHORIZED', 'message' => 'Invalid username or password'],
            self::json($response)['error'],
        );
    }

    public function testTheRightPasswordOfADeniedUserAnswers403WithoutTokens(): void
    {
        $this->user(status: 'denied');

        $response = $this->send(['username' => 'bob', 'password' => self::PASSWORD]);

        $this->assertSame(403, $response->status());
        $this->assertSame(
            ['code' => 'USER_DENIED', 'message' => 'This account has been denied access'],
            self::json($response)['error'],
        );
        $this->assertStringNotContainsString('refreshToken', $response->body());
        $this->assertSame([], self::cookies($response));
        $this->assertSame(0, $this->rowCount('refresh_tokens'));
    }

    public function testThePasswordIsNotTrimmed(): void
    {
        $this->user(digest: password_hash('  x  ', PASSWORD_BCRYPT, ['cost' => 4]));

        $this->assertSame(401, $this->send(['username' => 'bob', 'password' => 'x'])->status());
        $this->assertSame(201, $this->send(['username' => 'bob', 'password' => '  x  '])->status());
    }

    public function testAnOverLongPasswordSimplyFails(): void
    {
        $this->user();

        $response = $this->send(['username' => 'bob', 'password' => str_repeat('p', 200)]);

        $this->assertSame(401, $response->status());
    }

    /**
     * @return array<string, array{array<string, mixed>|string, list<string>}>
     */
    public static function invalidBodies(): array
    {
        return [
            'nothing' => ['{}', ['username should not be empty', 'password should not be empty']],
            'empty values' => [
                ['username' => '', 'password' => ''],
                ['username should not be empty', 'password should not be empty'],
            ],
            'whitespace-only username' => [
                ['username' => " \t ", 'password' => self::PASSWORD],
                ['username should not be empty'],
            ],
            'missing password' => [['username' => 'bob'], ['password should not be empty']],
            'wrong types' => [
                ['username' => 42, 'password' => ['a']],
                ['username must be a string', 'password must be a string'],
            ],
        ];
    }

    /**
     * @param array<string, mixed>|string $body
     * @param list<string>                $details
     */
    #[DataProvider('invalidBodies')]
    public function testInvalidBodiesAnswer400(array|string $body, array $details): void
    {
        $this->user();

        $response = $this->send($body);

        $this->assertSame(400, $response->status());
        $this->assertSame('VALIDATION_FAILED', self::json($response)['error']['code']);
        $this->assertSame($details, self::json($response)['error']['details']);
        $this->assertSame([], self::cookies($response));
    }

    public function testAWhitespaceOnlyPasswordIsNotEmpty(): void
    {
        $this->user();

        $this->assertSame(401, $this->send(['username' => 'bob', 'password' => '   '])->status());
    }

    private function user(string $status = 'approved', ?string $digest = null, bool $isAdmin = false): int
    {
        return $this->fixtures->user(
            username: 'bob',
            email: 'bob@example.com',
            passwordDigest: $digest ?? self::$digest,
            isAdmin: $isAdmin,
            status: $status,
        );
    }

    /**
     * @param array<string, mixed>|string $body
     */
    private function send(array|string $body): Response
    {
        $router = new Router();
        $router->post(self::PATH, LoginHandler::class);

        return $this->post($router, self::PATH, $body);
    }
}
