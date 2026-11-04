<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Auth\AccessToken;
use Pidgey\Auth\AccessTokenClaims;
use Pidgey\Auth\AccountEdit;
use Pidgey\Auth\AccountEditOutcome;
use Pidgey\Auth\AccountEditResult;
use Pidgey\Auth\AuthenticatedUser;
use Pidgey\Auth\Base64Url;
use Pidgey\Auth\Gate;
use Pidgey\Auth\Passwords;
use Pidgey\Auth\RefreshTokenValue;
use Pidgey\Auth\SecretKeys;
use Pidgey\Auth\SessionRotation;
use Pidgey\Auth\Sessions;
use Pidgey\Auth\SessionTokens;
use Pidgey\Auth\SessionUuid;
use Pidgey\Container;
use Pidgey\Http\BadRequestException;
use Pidgey\Http\ConflictException;
use Pidgey\Http\Cors;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\FieldRules;
use Pidgey\Http\FrontController;
use Pidgey\Http\Handler\AccountHandler;
use Pidgey\Http\HandlerFactory;
use Pidgey\Http\HttpException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\LockedException;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use Pidgey\Http\Route;
use Pidgey\Http\RouteMatch;
use Pidgey\Http\Router;
use Pidgey\Http\TrustedOrigins;
use Pidgey\Http\UnauthorizedException;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Http\Validator;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Infra\SystemWallClock;
use Pidgey\Infra\Transaction;
use Pidgey\Model\AccountEditLockout;
use Pidgey\Model\Row;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\AccountEditLockoutRepository;
use Pidgey\Repository\PasswordResetTokenRepository;
use Pidgey\Repository\RefreshTokenRepository;
use Pidgey\Repository\Sql;
use Pidgey\Repository\UserRepository;
use Pidgey\Tests\Support\SessionEndpointTestCase;

#[CoversClass(AccountHandler::class)]
#[UsesClass(AccessToken::class)]
#[UsesClass(AccessTokenClaims::class)]
#[UsesClass(AccountEdit::class)]
#[UsesClass(AccountEditLockout::class)]
#[UsesClass(AccountEditLockoutRepository::class)]
#[UsesClass(AccountEditOutcome::class)]
#[UsesClass(AccountEditResult::class)]
#[UsesClass(AuthenticatedUser::class)]
#[UsesClass(BadRequestException::class)]
#[UsesClass(Base64Url::class)]
#[UsesClass(ConflictException::class)]
#[UsesClass(Container::class)]
#[UsesClass(Cors::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(ErrorFormatter::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(FieldRules::class)]
#[UsesClass(FrontController::class)]
#[UsesClass(Gate::class)]
#[UsesClass(HandlerFactory::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(LockedException::class)]
#[UsesClass(Passwords::class)]
#[UsesClass(PasswordResetTokenRepository::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(ProcessingRequest::class)]
#[UsesClass(RefreshTokenRepository::class)]
#[UsesClass(RefreshTokenValue::class)]
#[UsesClass(Request::class)]
#[UsesClass(RequestHandler::class)]
#[UsesClass(Response::class)]
#[UsesClass(Route::class)]
#[UsesClass(RouteMatch::class)]
#[UsesClass(Router::class)]
#[UsesClass(Row::class)]
#[UsesClass(SecretKeys::class)]
#[UsesClass(SessionRotation::class)]
#[UsesClass(Sessions::class)]
#[UsesClass(SessionTokens::class)]
#[UsesClass(SessionUuid::class)]
#[UsesClass(Sql::class)]
#[UsesClass(SystemWallClock::class)]
#[UsesClass(Transaction::class)]
#[UsesClass(TrustedOrigins::class)]
#[UsesClass(UnauthorizedException::class)]
#[UsesClass(User::class)]
#[UsesClass(UserRepository::class)]
#[UsesClass(UserStatus::class)]
#[UsesClass(ValidationFailedException::class)]
#[UsesClass(Validator::class)]
final class AccountHandlerTest extends SessionEndpointTestCase
{
    private const PATH = '/auth/account.json';

    /** A password generated once for the whole class, so no password literal is hardcoded. */
    private static string $password;

    /** A password that never matches, generated once for the whole class. */
    private static string $wrongPassword;

    /** A cheap bcrypt digest of the password. */
    private static string $digest;

    private Router $router;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$password = self::randomPassword('pw-');
        self::$wrongPassword = self::randomPassword('wrong-');
        self::$digest = password_hash(self::$password, PASSWORD_BCRYPT, ['cost' => 4]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = require __DIR__ . '/../../../../config/routes.php';
    }

    public function testWithoutACookieTheGateAnswers401(): void
    {
        $user = $this->user('bob', 'bob@example.com');

        $response = $this->send(['currentPassword' => self::$password, 'username' => 'bobby']);

        $this->assertGateRefused($response, $user);
    }

    public function testAnInvalidCookieIsRefusedByTheGate(): void
    {
        $user = $this->user('bob', 'bob@example.com');

        $response = $this->send(
            ['currentPassword' => self::$password, 'username' => 'bobby'],
            ['access_token' => 'not-a-jwt'],
        );

        $this->assertGateRefused($response, $user);
    }

    /**
     * @return array<string, array{UserStatus}>
     */
    public static function unapprovedStatuses(): array
    {
        return ['pending' => [UserStatus::Pending], 'denied' => [UserStatus::Denied]];
    }

    #[DataProvider('unapprovedStatuses')]
    public function testAnUnapprovedUserIsRefusedByTheGate(UserStatus $status): void
    {
        $user = $this->user('bob', 'bob@example.com', $status);

        $response = $this->sendAs($user, ['currentPassword' => self::$password, 'username' => 'bobby']);

        $this->assertGateRefused($response, $user);
    }

    public function testEditsTheUsername(): void
    {
        $user = $this->user('bob', 'bob@example.com');

        $response = $this->sendAs($user, ['currentPassword' => self::$password, 'username' => '  bobby ']);

        $this->assertSame(200, $response->status());
        $this->assertSame(['username' => 'bobby', 'email' => 'bob@example.com'], self::json($response));
        $this->assertSame('bobby', $this->row('users', 'id', $user->id)['username']);
    }

    public function testEditsTheEmail(): void
    {
        $user = $this->user('bob', 'bob@example.com');

        $response = $this->send(
            ['currentPassword' => self::$password, 'email' => ' bobby@example.com '],
            $this->accessCookie($user),
        );

        $this->assertSame(200, $response->status());
        $this->assertSame(['username' => 'bob', 'email' => 'bobby@example.com'], self::json($response));
    }

    public function testEditsThePasswordAndTheNewOneLogsIn(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        // Padded to one space on each side: the surrounding spaces are kept, not trimmed.
        $newPassword = str_pad(self::randomPassword('new-', 8), 22, ' ', STR_PAD_BOTH);

        $response = $this->send(
            ['currentPassword' => self::$password, 'newPassword' => $newPassword],
            $this->accessCookie($user),
        );
        $login = $this->login($newPassword);
        $oldLogin = $this->login(self::$password);

        $this->assertSame(200, $response->status());
        $this->assertSame(['username' => 'bob', 'email' => 'bob@example.com'], self::json($response));
        $this->assertSame(201, $login->status());
        $this->assertSame(401, $oldLogin->status());
    }

    public function testNullOptionalFieldsAreNotPresent(): void
    {
        $user = $this->user('bob', 'bob@example.com');

        $response = $this->send(
            ['currentPassword' => self::$password, 'username' => 'bobby', 'email' => null, 'newPassword' => null],
            $this->accessCookie($user),
        );

        $this->assertSame(200, $response->status());
        $this->assertSame(['username' => 'bobby', 'email' => 'bob@example.com'], self::json($response));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function nothingToChangeBodies(): array
    {
        return [
            'only the current password' => [[]],
            'every change null' => [['username' => null, 'email' => null, 'newPassword' => null]],
        ];
    }

    /**
     * @param array<string, mixed> $changes
     */
    #[DataProvider('nothingToChangeBodies')]
    public function testNothingToChangeIsABadRequestAndNotCounted(array $changes): void
    {
        $user = $this->user('bob', 'bob@example.com');

        $response = $this->sendAs($user, ['currentPassword' => self::$wrongPassword, ...$changes]);

        $this->assertError($response, 400, 'BAD_REQUEST', AccountHandler::NOTHING_TO_CHANGE);
        $this->assertNull($this->lockoutRow($user->id));
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function invalidBodies(): array
    {
        return [
            'null current password' => [
                ['currentPassword' => null, 'username' => 'bobby'],
                ['currentPassword should not be empty'],
            ],
            'empty current password' => [
                ['currentPassword' => '', 'username' => 'bobby'],
                ['currentPassword should not be empty'],
            ],
            'empty username' => [['username' => ' '], ['username should not be empty']],
            'username too long' => [
                ['username' => str_repeat('a', 256)],
                ['username must be shorter than or equal to 255 characters'],
            ],
            'username not a string' => [['username' => 7], ['username must be a string']],
            'empty email' => [['email' => ''], ['email must be an email']],
            'bad email' => [['email' => 'bob'], ['email must be an email']],
            'email too long' => [
                ['email' => self::email(256)],
                ['email must be an email', 'email must be shorter than or equal to 255 characters'],
            ],
            'new password too short' => [
                ['newPassword' => str_repeat('a', 7)],
                ['newPassword must be longer than or equal to 8 characters'],
            ],
            'new password over 72 bytes' => [
                ['newPassword' => str_repeat('a', 73)],
                ['newPassword must be shorter than or equal to 72 bytes'],
            ],
            'new password with a NUL byte' => [
                ['newPassword' => implode(chr(0), ['abcd', 'efgh'])],
                ['newPassword must not contain a NUL byte'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string>         $details
     */
    #[DataProvider('invalidBodies')]
    public function testValidationFailuresAreNotCounted(array $body, array $details): void
    {
        $user = $this->user('bob', 'bob@example.com');

        $response = $this->sendAs($user, ['currentPassword' => self::$wrongPassword, ...$body]);

        $this->assertSame(400, $response->status());
        $this->assertSame('VALIDATION_FAILED', self::json($response)['error']['code']);
        $this->assertSame($details, self::json($response)['error']['details']);
        $this->assertNull($this->lockoutRow($user->id));
        $this->assertSame('bob', $this->row('users', 'id', $user->id)['username']);
    }

    public function testAMissingCurrentPasswordFailsValidation(): void
    {
        $user = $this->user('bob', 'bob@example.com');

        $response = $this->sendAs($user, ['username' => 'bobby']);

        $this->assertSame(400, $response->status());
        $this->assertSame(['currentPassword should not be empty'], self::json($response)['error']['details']);
        $this->assertNull($this->lockoutRow($user->id));
    }

    public function testLimitsAtTheBoundaryAreAccepted(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $username = str_repeat('a', 255);
        $email = self::email(254);

        $response = $this->send(
            [
                'currentPassword' => self::$password,
                'username' => $username,
                'email' => $email,
                'newPassword' => str_repeat('é', 36),
            ],
            $this->accessCookie($user),
        );

        $this->assertSame(200, $response->status());
        $this->assertSame(['username' => $username, 'email' => $email], self::json($response));
    }

    public function testAWrongCurrentPasswordIsCounted(): void
    {
        $user = $this->user('bob', 'bob@example.com');

        $response = $this->sendAs($user, ['currentPassword' => self::$wrongPassword, 'username' => 'bobby']);

        $this->assertError($response, 400, 'BAD_REQUEST', 'Invalid current password');
        $this->assertSame('1', (string) $this->lockoutRow($user->id)['failed_attempts']);
    }

    public function testATakenUsernameAnswers409(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $this->user('carol', 'carol@example.com');

        $response = $this->sendAs($user, ['currentPassword' => self::$password, 'username' => 'Carol']);

        $this->assertError($response, 409, 'USERNAME_TAKEN', 'Username already in use');
        $this->assertSame('1', (string) $this->lockoutRow($user->id)['failed_attempts']);
    }

    public function testATakenEmailAnswers409(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $this->user('carol', 'carol@example.com');

        $response = $this->send(
            ['currentPassword' => self::$password, 'email' => 'carol@example.com'],
            $this->accessCookie($user),
        );

        $this->assertError($response, 409, 'EMAIL_TAKEN', 'Email already in use');
    }

    public function testACaseOnlyUsernameChangeIsAllowed(): void
    {
        $user = $this->user('Bob', 'bob@example.com');

        $response = $this->sendAs($user, ['currentPassword' => self::$password, 'username' => 'bob']);

        $this->assertSame(200, $response->status());
        $this->assertSame('bob', self::json($response)['username']);
    }

    public function testLocksAfterTheMaximumFailures(): void
    {
        putenv('ACCOUNT_EDIT_MAX_ATTEMPTS=2');
        $user = $this->user('bob', 'bob@example.com');
        $cookie = $this->accessCookie($user);
        $wrongAttempt = ['currentPassword' => self::$wrongPassword, 'username' => 'bobby'];

        $statuses = [
            $this->send($wrongAttempt, $cookie)->status(),
            $this->send($wrongAttempt, $cookie)->status(),
        ];
        $locked = $this->send(['currentPassword' => self::$password, 'username' => 'bobby'], $cookie);

        $this->assertSame([400, 400], $statuses);
        $this->assertError($locked, 423, 'LOCKED', AccountEdit::LOCKED_MESSAGE);
        $this->assertSame('bob', $this->row('users', 'id', $user->id)['username']);
    }

    public function testAnExpiredLockIsCleared(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $this->setLockout($user->id, 9, self::utc('-1 second'));

        $response = $this->sendAs($user, ['currentPassword' => self::$password, 'username' => 'bobby']);

        $this->assertSame(200, $response->status());
        $this->assertSame('0', (string) $this->lockoutRow($user->id)['failed_attempts']);
        $this->assertNull($this->lockoutRow($user->id)['locked_until']);
    }

    public function testAnEmailChangeInvalidatesResetTokens(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $token = $this->resetToken($user->id);

        $this->sendAs($user, ['currentPassword' => self::$password, 'email' => 'bobby@example.com']);

        $this->assertNotNull($this->resetTokenUsedAt($token));
    }

    public function testAUsernameChangeKeepsResetTokens(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $token = $this->resetToken($user->id);

        $this->sendAs($user, ['currentPassword' => self::$password, 'username' => 'bobby']);

        $this->assertNull($this->resetTokenUsedAt($token));
    }

    public function testACrossSiteRequestIsRejectedBeforeTheGate(): void
    {
        $user = $this->user('bob', 'bob@example.com');

        $response = $this->patch(
            $this->router,
            self::PATH,
            ['currentPassword' => self::$password, 'username' => 'bobby'],
            $this->accessCookie($user),
            ['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'https://evil.example'],
        );

        $this->assertSame(403, $response->status());
        $this->assertSame('FORBIDDEN', self::json($response)['error']['code']);
        $this->assertSame('bob', $this->row('users', 'id', $user->id)['username']);
        $this->assertNull($this->lockoutRow($user->id));
    }

    public function testTheGate401CarriesTheCorsHeadersForATrustedOrigin(): void
    {
        putenv('ALLOWED_ORIGINS=https://admin.test');
        $user = $this->user('bob', 'bob@example.com');
        $body = ['currentPassword' => self::$password, 'username' => 'bobby'];

        $plain = $this->send($body);
        $trusted = $this->patch($this->router, self::PATH, $body, [], ['Origin' => 'https://admin.test']);

        $this->assertGateRefused($trusted, $user);
        $this->assertSame($plain->status(), $trusted->status());
        $this->assertSame(self::envelope($plain), self::envelope($trusted));
        $this->assertSame([
            'Content-Type: application/json',
            'Vary: Origin',
            'Access-Control-Allow-Origin: https://admin.test',
            'Access-Control-Allow-Credentials: true',
        ], $trusted->headers());
        $this->assertSame(['Content-Type: application/json', 'Vary: Origin'], $plain->headers());
    }

    /**
     * Builds an email address of the given length (at least 200): `bob@` and 63-character labels.
     *
     * FILTER_VALIDATE_EMAIL refuses 255 characters and more, so 254 is the longest valid one.
     */
    private static function email(int $length): string
    {
        $labels = str_repeat('a', 63) . '.' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.';

        return 'bob@' . $labels . str_repeat('d', $length - 4 - strlen($labels) - 4) . '.com';
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $cookies
     */
    private function send(array $body, array $cookies = []): Response
    {
        return $this->patch($this->router, self::PATH, $body, $cookies);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function sendAs(User $user, array $body): Response
    {
        return $this->send($body, $this->accessCookie($user));
    }

    private function login(string $password): Response
    {
        return $this->post($this->router, '/auth/login.json', ['username' => 'bob', 'password' => $password]);
    }

    private function user(string $username, string $email, UserStatus $status = UserStatus::Approved): User
    {
        $id = $this->fixtures->user(
            username: $username,
            email: $email,
            passwordDigest: self::$digest,
            status: $status->value,
        );

        return (new UserRepository($this->pdo))->findById($id) ?? self::fail('The fixture user is missing.');
    }

    /**
     * Returns the error envelope without its timestamp.
     *
     * @return array<string, mixed>
     */
    private static function envelope(Response $response): array
    {
        $body = self::json($response);
        unset($body['timestamp']);

        return $body;
    }

    private function assertGateRefused(Response $response, User $user): void
    {
        $this->assertError($response, 401, 'UNAUTHORIZED', Gate::UNAUTHORIZED_MESSAGE);
        $this->assertNull($this->lockoutRow($user->id));
        $this->assertSame($user->username, $this->row('users', 'id', $user->id)['username']);
    }

    private function assertError(Response $response, int $status, string $code, string $message): void
    {
        $body = self::json($response);

        $this->assertSame($status, $response->status());
        $this->assertSame(['error', 'statusCode', 'timestamp'], array_keys($body));
        $this->assertSame(['code' => $code, 'message' => $message], $body['error']);
        $this->assertSame($status, $body['statusCode']);
    }
}
