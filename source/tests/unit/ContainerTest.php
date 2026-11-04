<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\AccessToken;
use Pidgey\Auth\AccessTokenClaims;
use Pidgey\Auth\AccountEdit;
use Pidgey\Auth\Base64Url;
use Pidgey\Auth\Login;
use Pidgey\Auth\Passwords;
use Pidgey\Auth\Registration;
use Pidgey\Auth\SecretKeys;
use Pidgey\Auth\SessionRotation;
use Pidgey\Auth\Sessions;
use Pidgey\Container;
use Pidgey\Infra\ConnectionFactory;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\SystemWallClock;
use Pidgey\Infra\Transaction;
use Pidgey\Infra\UserApproval;
use Pidgey\Infra\WallClock;
use Pidgey\Repository\AccountEditLockoutRepository;
use Pidgey\Repository\ApiTokenRepository;
use Pidgey\Repository\AuthorizationRequestRepository;
use Pidgey\Repository\OrganizationRepository;
use Pidgey\Repository\PasswordResetTokenRepository;
use Pidgey\Repository\RefreshTokenRepository;
use Pidgey\Repository\SmtpConnectionRepository;
use Pidgey\Repository\UserRepository;
use RuntimeException;

#[CoversClass(Container::class)]
#[UsesClass(AccessToken::class)]
#[UsesClass(AccessTokenClaims::class)]
#[UsesClass(AccountEdit::class)]
#[UsesClass(Base64Url::class)]
#[UsesClass(Login::class)]
#[UsesClass(Passwords::class)]
#[UsesClass(Registration::class)]
#[UsesClass(SecretKeys::class)]
#[UsesClass(SessionRotation::class)]
#[UsesClass(Sessions::class)]
#[UsesClass(SystemWallClock::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(Transaction::class)]
#[UsesClass(UserApproval::class)]
#[UsesClass(OrganizationRepository::class)]
#[UsesClass(UserRepository::class)]
#[UsesClass(RefreshTokenRepository::class)]
#[UsesClass(PasswordResetTokenRepository::class)]
#[UsesClass(AuthorizationRequestRepository::class)]
#[UsesClass(AccountEditLockoutRepository::class)]
#[UsesClass(ApiTokenRepository::class)]
#[UsesClass(SmtpConnectionRepository::class)]
final class ContainerTest extends TestCase
{
    private const VARIABLES = [
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
        'SECRET_KEY',
        'PREVIOUS_SECRET_KEYS',
        'ACCESS_TOKEN_TTL',
        'REFRESH_TOKEN_TTL',
        'USER_APPROVAL_REQUIRED',
        'ACCOUNT_EDIT_MAX_ATTEMPTS',
        'ACCOUNT_EDIT_LOCK_TTL',
    ];

    private const SECRET_KEY = 'container-test-secret-key-0123456789abcdef';

    /** @var array<string, string|false> */
    private array $saved = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->saved[$name] = getenv($name);
        }

        putenv('DB_HOST=db.example');
        putenv('DB_PORT=3307');
        putenv('DB_NAME=pidgey_example_test');
        putenv('DB_USER=someone');
        putenv('DB_PASSWORD=secret');
        putenv('SECRET_KEY=' . self::SECRET_KEY);
        putenv('PREVIOUS_SECRET_KEYS');
        putenv('ACCESS_TOKEN_TTL');
        putenv('REFRESH_TOKEN_TTL');
        putenv('USER_APPROVAL_REQUIRED');
        putenv('ACCOUNT_EDIT_MAX_ATTEMPTS');
        putenv('ACCOUNT_EDIT_LOCK_TTL');
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }
    }

    public function testConstructorConnectsNothing(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->never())->method('create');
        $factory->expects($this->never())->method('createServer');

        new Container($factory);
    }

    public function testConstructorNeedsNoDatabaseVariables(): void
    {
        putenv('DB_HOST');

        $this->assertInstanceOf(Container::class, new Container());
    }

    public function testPdoConnectsOnceWithTheEnvironmentConfig(): void
    {
        $pdo = $this->createStub(PDO::class);
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with(new DatabaseConfig('db.example', 3307, 'pidgey_example_test', 'someone', 'secret'))
            ->willReturn($pdo);
        $container = new Container($factory);

        $this->assertSame($pdo, $container->pdo());
        $this->assertSame($pdo, $container->pdo());
    }

    public function testPdoReportsAMissingVariableByName(): void
    {
        putenv('DB_HOST');
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->never())->method('create');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB_HOST');

        (new Container($factory))->pdo();
    }

    public function testTransactionIsMemoizedAndWrapsTheSamePdo(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->once())->method('commit')->willReturn(true);
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($pdo);
        $container = new Container($factory);

        $transaction = $container->transaction();

        $this->assertSame($transaction, $container->transaction());
        $this->assertSame($pdo, $container->pdo());
        $this->assertSame('ok', $transaction->run(static fn (): string => 'ok'));
    }

    public function testOrganizationRepositoryIsMemoizedOnASinglePdo(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($this->createStub(PDO::class));
        $container = new Container($factory);

        $repository = $container->organizationRepository();

        $this->assertInstanceOf(OrganizationRepository::class, $repository);
        $this->assertSame($repository, $container->organizationRepository());
        $container->userRepository();
        $container->transaction();
    }

    public function testUserRepositoryIsMemoizedOnASinglePdo(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($this->createStub(PDO::class));
        $container = new Container($factory);

        $repository = $container->userRepository();

        $this->assertInstanceOf(UserRepository::class, $repository);
        $this->assertSame($repository, $container->userRepository());
        $container->organizationRepository();
        $container->pdo();
    }

    public function testRefreshTokenRepositoryIsMemoizedOnASinglePdo(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($this->createStub(PDO::class));
        $container = new Container($factory);

        $repository = $container->refreshTokenRepository();

        $this->assertInstanceOf(RefreshTokenRepository::class, $repository);
        $this->assertSame($repository, $container->refreshTokenRepository());
        $container->userRepository();
        $container->pdo();
    }

    public function testPasswordResetTokenRepositoryIsMemoizedOnASinglePdo(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($this->createStub(PDO::class));
        $container = new Container($factory);

        $repository = $container->passwordResetTokenRepository();

        $this->assertInstanceOf(PasswordResetTokenRepository::class, $repository);
        $this->assertSame($repository, $container->passwordResetTokenRepository());
        $container->userRepository();
        $container->pdo();
    }

    public function testAuthorizationRequestRepositoryIsMemoizedOnASinglePdo(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($this->createStub(PDO::class));
        $container = new Container($factory);

        $repository = $container->authorizationRequestRepository();

        $this->assertInstanceOf(AuthorizationRequestRepository::class, $repository);
        $this->assertSame($repository, $container->authorizationRequestRepository());
        $container->userRepository();
        $container->pdo();
    }

    public function testAccountEditLockoutRepositoryIsMemoizedOnASinglePdo(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($this->createStub(PDO::class));
        $container = new Container($factory);

        $repository = $container->accountEditLockoutRepository();

        $this->assertInstanceOf(AccountEditLockoutRepository::class, $repository);
        $this->assertSame($repository, $container->accountEditLockoutRepository());
        $container->userRepository();
        $container->pdo();
    }

    public function testApiTokenRepositoryIsMemoizedOnASinglePdo(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($this->createStub(PDO::class));
        $container = new Container($factory);

        $repository = $container->apiTokenRepository();

        $this->assertInstanceOf(ApiTokenRepository::class, $repository);
        $this->assertSame($repository, $container->apiTokenRepository());
        $container->organizationRepository();
        $container->pdo();
    }

    public function testSmtpConnectionRepositoryIsMemoizedOnASinglePdo(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($this->createStub(PDO::class));
        $container = new Container($factory);

        $repository = $container->smtpConnectionRepository();

        $this->assertInstanceOf(SmtpConnectionRepository::class, $repository);
        $this->assertSame($repository, $container->smtpConnectionRepository());
        $container->organizationRepository();
        $container->pdo();
    }

    public function testWallClockDefaultsToTheSystemClockAndIsMemoized(): void
    {
        $container = new Container($this->unusedConnections());

        $clock = $container->wallClock();

        $this->assertInstanceOf(SystemWallClock::class, $clock);
        $this->assertSame($clock, $container->wallClock());
    }

    public function testWallClockCanBeInjected(): void
    {
        $clock = $this->fixedClock(1_800_000_000);

        $this->assertSame($clock, (new Container($this->unusedConnections(), $clock))->wallClock());
    }

    public function testAccessTokenIsMemoizedAndOpensNoConnection(): void
    {
        $container = new Container($this->unusedConnections());

        $accessToken = $container->accessToken();

        $this->assertInstanceOf(AccessToken::class, $accessToken);
        $this->assertSame($accessToken, $container->accessToken());
    }

    public function testAccessTokenUsesTheSecretKeyTheDefaultTtlAndTheContainerClock(): void
    {
        $container = new Container($this->unusedConnections(), $this->fixedClock(1_800_000_000));

        $token = $container->accessToken()->issue(7, '0b8f2c3e-1d4a-4b6c-9e7f-a1b2c3d4e5f6', 'alice', false);
        $claims = (new AccessToken(new SecretKeys(self::SECRET_KEY), 900, $this->fixedClock(1_800_000_000)))
            ->verify($token);

        $this->assertNotNull($claims);
        $this->assertSame(1_800_000_000, $claims->issuedAt);
        $this->assertSame(1_800_000_900, $claims->expiresAt);
    }

    public function testAccessTokenReadsTheTtlFromTheEnvironment(): void
    {
        putenv('ACCESS_TOKEN_TTL=60');
        $container = new Container($this->unusedConnections(), $this->fixedClock(1_800_000_000));

        $token = $container->accessToken()->issue(7, '0b8f2c3e-1d4a-4b6c-9e7f-a1b2c3d4e5f6', 'alice', false);

        $this->assertSame(1_800_000_060, $container->accessToken()->verify($token)?->expiresAt);
    }

    public function testAccessTokenReportsAMissingSecretKey(): void
    {
        putenv('SECRET_KEY');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SECRET_KEY');

        (new Container($this->unusedConnections()))->accessToken();
    }

    public function testSessionsIsLazyAndMemoizedOnASinglePdo(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($this->createStub(PDO::class));
        $container = new Container($factory);

        $sessions = $container->sessions();

        $this->assertInstanceOf(Sessions::class, $sessions);
        $this->assertSame($sessions, $container->sessions());
        $container->refreshTokenRepository();
        $container->transaction();
    }

    public function testSessionRotationIsMemoizedOnASinglePdo(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($this->createStub(PDO::class));
        $container = new Container($factory);

        $rotation = $container->sessionRotation();

        $this->assertInstanceOf(SessionRotation::class, $rotation);
        $this->assertSame($rotation, $container->sessionRotation());
        $container->sessions();
    }

    public function testSessionsReportsAnInvalidRefreshTokenTtl(): void
    {
        putenv('REFRESH_TOKEN_TTL=soon');
        $factory = $this->createStub(ConnectionFactory::class);
        $factory->method('create')->willReturn($this->createStub(PDO::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('REFRESH_TOKEN_TTL');

        (new Container($factory))->sessions();
    }

    public function testPasswordsIsMemoizedAndOpensNoConnection(): void
    {
        $container = new Container($this->unusedConnections());

        $passwords = $container->passwords();

        $this->assertInstanceOf(Passwords::class, $passwords);
        $this->assertSame($passwords, $container->passwords());
    }

    public function testRegistrationIsMemoizedOnASinglePdo(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($this->createStub(PDO::class));
        $container = new Container($factory);

        $registration = $container->registration();

        $this->assertInstanceOf(Registration::class, $registration);
        $this->assertSame($registration, $container->registration());
        $container->sessions();
        $container->transaction();
    }

    public function testLoginIsMemoizedOnASinglePdo(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($this->createStub(PDO::class));
        $container = new Container($factory);

        $login = $container->login();

        $this->assertInstanceOf(Login::class, $login);
        $this->assertSame($login, $container->login());
        $container->sessions();
    }

    public function testAccountEditIsMemoizedOnASinglePdo(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($this->createStub(PDO::class));
        $container = new Container($factory);

        $accountEdit = $container->accountEdit();

        $this->assertInstanceOf(AccountEdit::class, $accountEdit);
        $this->assertSame($accountEdit, $container->accountEdit());
        $container->transaction();
    }

    public function testAccountEditRejectsAnInvalidMaxAttempts(): void
    {
        $factory = $this->createStub(ConnectionFactory::class);
        $factory->method('create')->willReturn($this->createStub(PDO::class));
        putenv('ACCOUNT_EDIT_MAX_ATTEMPTS=many');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ACCOUNT_EDIT_MAX_ATTEMPTS');

        (new Container($factory))->accountEdit();
    }

    public function testAccountEditRejectsAnInvalidLockTtl(): void
    {
        $factory = $this->createStub(ConnectionFactory::class);
        $factory->method('create')->willReturn($this->createStub(PDO::class));
        putenv('ACCOUNT_EDIT_LOCK_TTL=-1');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ACCOUNT_EDIT_LOCK_TTL');

        (new Container($factory))->accountEdit();
    }

    public function testUserApprovalIsRequiredByDefaultAndOpensNoConnection(): void
    {
        $this->assertTrue((new Container($this->unusedConnections()))->userApprovalRequired());
    }

    public function testUserApprovalFollowsTheEnvironment(): void
    {
        $container = new Container($this->unusedConnections());

        putenv('USER_APPROVAL_REQUIRED=false');
        $this->assertFalse($container->userApprovalRequired());

        putenv('USER_APPROVAL_REQUIRED=FALSE');
        $this->assertTrue($container->userApprovalRequired());
    }

    private function unusedConnections(): ConnectionFactory
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->never())->method('create');
        $factory->expects($this->never())->method('createServer');

        return $factory;
    }

    private function fixedClock(int $timestamp): WallClock
    {
        return new class ($timestamp) implements WallClock {
            public function __construct(private readonly int $timestamp)
            {
            }

            public function now(): DateTimeImmutable
            {
                return (new DateTimeImmutable())->setTimestamp($this->timestamp);
            }
        };
    }
}
