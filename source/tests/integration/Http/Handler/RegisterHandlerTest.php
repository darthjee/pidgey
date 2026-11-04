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
use Pidgey\Auth\Registration;
use Pidgey\Auth\SecretKeys;
use Pidgey\Auth\SessionResponse;
use Pidgey\Auth\SessionRotation;
use Pidgey\Auth\Sessions;
use Pidgey\Auth\SessionTokens;
use Pidgey\Auth\SessionUuid;
use Pidgey\Auth\UserPayload;
use Pidgey\Container;
use Pidgey\Http\ConflictException;
use Pidgey\Http\Cors;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\FieldRules;
use Pidgey\Http\FrontController;
use Pidgey\Http\Handler\RegisterHandler;
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
use Pidgey\Http\ValidationFailedException;
use Pidgey\Http\Validator;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Infra\SystemWallClock;
use Pidgey\Infra\Transaction;
use Pidgey\Infra\UserApproval;
use Pidgey\Model\Organization;
use Pidgey\Model\Row;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\OrganizationRepository;
use Pidgey\Repository\RefreshTokenRepository;
use Pidgey\Repository\Sql;
use Pidgey\Repository\UserRepository;
use Pidgey\Tests\Support\SessionEndpointTestCase;

#[CoversClass(RegisterHandler::class)]
#[UsesClass(AccessToken::class)]
#[UsesClass(AccessTokenClaims::class)]
#[UsesClass(AccessTokenCookie::class)]
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
#[UsesClass(HandlerFactory::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(Organization::class)]
#[UsesClass(OrganizationRepository::class)]
#[UsesClass(Passwords::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(ProcessingRequest::class)]
#[UsesClass(RefreshTokenRepository::class)]
#[UsesClass(RefreshTokenValue::class)]
#[UsesClass(Registration::class)]
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
#[UsesClass(TrustedOrigins::class)]
#[UsesClass(User::class)]
#[UsesClass(UserApproval::class)]
#[UsesClass(UserPayload::class)]
#[UsesClass(UserRepository::class)]
#[UsesClass(UserStatus::class)]
#[UsesClass(ValidationFailedException::class)]
#[UsesClass(Validator::class)]
final class RegisterHandlerTest extends SessionEndpointTestCase
{
    private const PATH = '/auth/register.json';


    public function testSuccessAnswers201WithTheUserPayloadAndTheRefreshToken(): void
    {
        $response = $this->send($this->body());
        $body = self::json($response);
        $user = $this->row('users', 'username', 'bob');

        $this->assertSame(201, $response->status());
        $this->assertSame(['user', 'refreshToken'], array_keys($body));
        $this->assertSame(
            ['id' => (int) $user['id'], 'username' => 'bob', 'email' => 'bob@example.com', 'isAdmin' => false],
            $body['user'],
        );
        $this->assertIsString($body['refreshToken']);
        $this->assertSame(
            RefreshTokenValue::hash($body['refreshToken']),
            $this->row('refresh_tokens', 'user_id', (int) $user['id'])['token_hash'],
        );
    }

    public function testSuccessSetsTheAccessTokenCookie(): void
    {
        $response = $this->send($this->body());
        $cookies = self::cookies($response);

        $this->assertCount(1, $cookies);
        $this->assertMatchesRegularExpression(
            '/\ASet-Cookie: access_token=([^;]+); HttpOnly; Secure; SameSite=Strict; Path=\/; Max-Age='
            . self::ACCESS_TOKEN_TTL . '\z/',
            $cookies[0],
        );
        preg_match('/access_token=([^;]+);/', $cookies[0], $match);
        $claims = (new AccessToken(new SecretKeys(self::SECRET_KEY), self::ACCESS_TOKEN_TTL, new SystemWallClock()))
            ->verify($match[1]);
        $this->assertSame((int) $this->row('users', 'username', 'bob')['id'], $claims?->userId);
    }

    public function testTheBodyCarriesNoStatusNoDigestAndNoAccessToken(): void
    {
        $response = $this->send($this->body());

        $this->assertStringNotContainsString('status', $response->body());
        $this->assertStringNotContainsString('pending', $response->body());
        $this->assertStringNotContainsString('$2y$', $response->body());
        $this->assertStringNotContainsString('organization', $response->body());
    }

    public function testFieldsAreTrimmedButThePasswordIsNot(): void
    {
        $response = $this->send([
            'username' => "  bob\t",
            'email' => ' bob@example.com ',
            'password' => '  12345678  ',
            'organizationName' => "  Bob's Shop  ",
        ]);

        $this->assertSame(201, $response->status());
        $user = $this->row('users', 'username', 'bob');
        $this->assertSame('bob@example.com', $user['email']);
        $this->assertTrue(password_verify('  12345678  ', (string) $user['password_digest']));
        $this->assertFalse(password_verify('12345678', (string) $user['password_digest']));
        $this->assertSame("Bob's Shop", $this->row('organizations', 'id', (int) $user['organization_id'])['name']);
    }

    public function testEveryValidationErrorIsReportedAtOnce(): void
    {
        $response = $this->send([
            'username' => str_repeat('u', 256),
            'email' => 'not-an-email',
            'password' => 'short',
            'organizationName' => str_repeat('o', 256),
        ]);

        $this->assertValidationFailed(
            [
                'username must be shorter than or equal to 255 characters',
                'email must be an email',
                'password must be longer than or equal to 8 characters',
                'organizationName must be shorter than or equal to 255 characters',
            ],
            $response,
        );
    }

    public function testMissingFieldsAreReportedAtOnce(): void
    {
        $this->assertValidationFailed(
            [
                'username should not be empty',
                'email should not be empty',
                'password should not be empty',
                'organizationName should not be empty',
            ],
            $this->send('{}'),
        );
    }

    public function testWhitespaceOnlyFieldsAreEmpty(): void
    {
        $response = $this->send([...$this->body(), 'username' => '   ', 'organizationName' => "\t "]);

        $this->assertValidationFailed(
            ['username should not be empty', 'organizationName should not be empty'],
            $response,
        );
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function invalidPasswords(): array
    {
        return [
            '73 bytes' => [str_repeat('p', 73), ['password must be shorter than or equal to 72 bytes']],
            '72 bytes of multi-byte characters plus one' => [
                str_repeat('é', 36) . 'x',
                ['password must be shorter than or equal to 72 bytes'],
            ],
            'NUL byte' => ["dummy\0value", ['password must not contain a NUL byte']],
        ];
    }

    /**
     * @param list<string> $details
     */
    #[DataProvider('invalidPasswords')]
    public function testInvalidPasswordsAreRejected(string $password, array $details): void
    {
        $this->assertValidationFailed($details, $this->send([...$this->body(), 'password' => $password]));
    }

    public function testA72BytePasswordIsAccepted(): void
    {
        $this->assertSame(201, $this->send([...$this->body(), 'password' => str_repeat('é', 36)])->status());
    }

    public function testATakenUsernameAnswers409(): void
    {
        $this->fixtures->user(username: 'BOB');

        $response = $this->send($this->body());

        $this->assertSame(409, $response->status());
        $this->assertSame(
            ['code' => 'USERNAME_TAKEN', 'message' => 'username is not available'],
            self::json($response)['error'],
        );
        $this->assertSame([], self::cookies($response));
    }

    public function testATakenEmailAnswers409(): void
    {
        $this->fixtures->user(email: 'bob@example.com');

        $response = $this->send($this->body());

        $this->assertSame(409, $response->status());
        $this->assertSame(
            ['code' => 'EMAIL_TAKEN', 'message' => 'email is not available'],
            self::json($response)['error'],
        );
        $this->assertSame([], self::cookies($response));
    }

    public function testAnUnexplainedIntegrityViolationAnswers500AndLogsNoUserData(): void
    {
        // A temporary unique index makes the organization insert fail with SQLSTATE 23000.
        $this->fixtures->organization("Bob's Shop");
        $this->pdo->exec('ALTER TABLE organizations ADD UNIQUE INDEX organizations_name_test (name)');

        try {
            $response = $this->send($this->body());
        } finally {
            $this->pdo->exec('ALTER TABLE organizations DROP INDEX organizations_name_test');
        }

        $this->assertSame(500, $response->status());
        $this->assertSame('INTERNAL_ERROR', self::json($response)['error']['code']);
        $this->assertCount(1, $this->logged);
        $this->assertStringContainsString('SQLSTATE 23000', $this->logged[0]);
        $this->assertStringNotContainsString("Bob's Shop", $this->logged[0]);
        $this->assertStringNotContainsString('bob@example.com', $this->logged[0]);
        $this->assertStringNotContainsString('Duplicate entry', $this->logged[0]);
    }

    /**
     * @param array<string, mixed>|string $body
     */
    /**
     * @return array<string, string> A valid body whose password is generated at runtime.
     */
    private function body(): array
    {
        return [
            'username' => 'bob',
            'email' => 'bob@example.com',
            'password' => 'pw-' . bin2hex(random_bytes(12)),
            'organizationName' => "Bob's Shop",
        ];
    }

    private function send(array|string $body): Response
    {
        $router = new Router();
        $router->post(self::PATH, RegisterHandler::class);

        return $this->post($router, self::PATH, $body);
    }

    /**
     * @param list<string> $details
     */
    private function assertValidationFailed(array $details, Response $response): void
    {
        $this->assertSame(400, $response->status());
        $this->assertSame('VALIDATION_FAILED', self::json($response)['error']['code']);
        $this->assertSame($details, self::json($response)['error']['details']);
        $this->assertSame(0, $this->rowCount('users'));
        $this->assertSame([], self::cookies($response));
    }
}
