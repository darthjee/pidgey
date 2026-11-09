<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit;

use DateTimeImmutable;
use Monolog\Handler\AbstractHandler;
use Monolog\Level;
use Monolog\Logger;
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
use Pidgey\Crypto\EncryptionKeys;
use Pidgey\Crypto\PasswordCipher;
use Pidgey\Infra\AppPath;
use Pidgey\Infra\Clock;
use Pidgey\Infra\ConnectionFactory;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\LoggerFactory;
use Pidgey\Infra\SystemClock;
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
use Pidgey\Smtp\SessionSettings;
use Psr\Log\NullLogger;
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
#[UsesClass(SystemClock::class)]
#[UsesClass(SystemWallClock::class)]
#[UsesClass(LoggerFactory::class)]
#[UsesClass(EncryptionKeys::class)]
#[UsesClass(PasswordCipher::class)]
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
#[UsesClass(AppPath::class)]
#[UsesClass(SessionSettings::class)]
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
        'SMTP_ENCRYPTION_KEY',
        'PREVIOUS_SMTP_ENCRYPTION_KEYS',
        'ACCESS_TOKEN_TTL',
        'REFRESH_TOKEN_TTL',
        'USER_APPROVAL_REQUIRED',
        'ACCOUNT_EDIT_MAX_ATTEMPTS',
        'ACCOUNT_EDIT_LOCK_TTL',
        'LOG_LEVEL',
        'SMTP_CONNECT_TIMEOUT',
        'SMTP_COMMAND_TIMEOUT',
        'SMTP_TOTAL_TIMEOUT',
        'SMTP_CA_FILE',
        'SMTP_EHLO_NAME',
    ];

    private const SECRET_KEY = 'container-test-secret-key-0123456789abcdef';
    private const SMTP_KEY = 'container-test-smtp-encryption-key-0123456789abcdef';
    private const PREVIOUS_SMTP_KEY = 'container-test-previous-smtp-encryption-key-0123456789';

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
        putenv('SMTP_ENCRYPTION_KEY=' . self::SMTP_KEY);
        putenv('PREVIOUS_SMTP_ENCRYPTION_KEYS');
        putenv('ACCESS_TOKEN_TTL');
        putenv('REFRESH_TOKEN_TTL');
        putenv('USER_APPROVAL_REQUIRED');
        putenv('ACCOUNT_EDIT_MAX_ATTEMPTS');
        putenv('ACCOUNT_EDIT_LOCK_TTL');
        putenv('LOG_LEVEL');
        putenv('SMTP_CONNECT_TIMEOUT');
        putenv('SMTP_COMMAND_TIMEOUT');
        putenv('SMTP_TOTAL_TIMEOUT');
        putenv('SMTP_CA_FILE');
        putenv('SMTP_EHLO_NAME');
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

    public function testClockDefaultsToTheSystemClockAndIsMemoized(): void
    {
        $container = new Container($this->unusedConnections());

        $clock = $container->clock();

        $this->assertInstanceOf(SystemClock::class, $clock);
        $this->assertSame($clock, $container->clock());
    }

    public function testClockCanBeInjected(): void
    {
        $clock = $this->createStub(Clock::class);

        $this->assertSame($clock, (new Container($this->unusedConnections(), clock: $clock))->clock());
    }

    public function testLoggerIsMemoizedAndOpensNoConnection(): void
    {
        $container = new Container($this->unusedConnections());

        $logger = $container->logger();

        $this->assertSame($logger, $container->logger());
    }

    public function testLoggerCanBeInjected(): void
    {
        $logger = new NullLogger();

        $this->assertSame($logger, (new Container($this->unusedConnections(), logger: $logger))->logger());
    }

    public function testLoggerDefaultsToThePidgeyChannelAtTheDefaultLevel(): void
    {
        $logger = (new Container($this->unusedConnections()))->logger();

        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertSame('pidgey', $logger->getName());
        $this->assertSame(Level::Info, $this->handlerLevel($logger));
    }

    public function testLoggerLevelFollowsTheEnvironment(): void
    {
        putenv('LOG_LEVEL=warning');

        $logger = (new Container($this->unusedConnections()))->logger();

        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertSame(Level::Warning, $this->handlerLevel($logger));
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

    public function testPasswordCipherIsMemoizedAndOpensNoConnection(): void
    {
        $container = new Container($this->unusedConnections());

        $cipher = $container->passwordCipher();

        $this->assertInstanceOf(PasswordCipher::class, $cipher);
        $this->assertSame($cipher, $container->passwordCipher());
        $this->assertSame($container->encryptionKeys(), $container->encryptionKeys());
    }

    public function testPasswordCipherUsesTheEnvironmentKeys(): void
    {
        putenv('SMTP_ENCRYPTION_KEY=' . self::PREVIOUS_SMTP_KEY);
        $stored = (new Container($this->unusedConnections()))->passwordCipher()->encrypt('pw', 3, 4);
        putenv('SMTP_ENCRYPTION_KEY=' . self::SMTP_KEY);
        putenv('PREVIOUS_SMTP_ENCRYPTION_KEYS=' . self::PREVIOUS_SMTP_KEY);
        $container = new Container($this->unusedConnections());

        $this->assertSame('pw', $container->passwordCipher()->decrypt($stored, 3, 4));
        $this->assertSame(EncryptionKeys::keyIdOf(self::SMTP_KEY), $container->encryptionKeys()->currentKeyId());
    }

    public function testPasswordCipherReportsAMissingSmtpEncryptionKey(): void
    {
        putenv('SMTP_ENCRYPTION_KEY');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SMTP_ENCRYPTION_KEY');

        (new Container($this->unusedConnections()))->passwordCipher();
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

    public function testSmtpSessionSettingsUseTheDefaultsAndOpenNoConnection(): void
    {
        $settings = (new Container($this->unusedConnections()))->smtpSessionSettings();

        $this->assertEquals(new SessionSettings(10.0, 10.0, 25.0, null, null), $settings);
    }

    public function testSmtpSessionSettingsFollowTheEnvironment(): void
    {
        putenv('SMTP_CONNECT_TIMEOUT=3');
        putenv('SMTP_COMMAND_TIMEOUT=4');
        putenv('SMTP_TOTAL_TIMEOUT=12');
        putenv('SMTP_CA_FILE=/etc/ssl/custom-ca.pem');
        putenv('SMTP_EHLO_NAME=mail.example.com');

        $settings = (new Container($this->unusedConnections()))->smtpSessionSettings();

        $this->assertSame(3.0, $settings->connectTimeout);
        $this->assertSame(4.0, $settings->commandTimeout);
        $this->assertSame(12.0, $settings->totalTimeout);
        $this->assertSame('/etc/ssl/custom-ca.pem', $settings->caFile);
        $this->assertSame('mail.example.com', $settings->ehloName);
    }

    public function testSmtpSessionSettingsResolveARelativeCaFile(): void
    {
        putenv('SMTP_CA_FILE=tests/fixtures/tls/ca.pem');

        $settings = (new Container($this->unusedConnections()))->smtpSessionSettings();

        $this->assertSame(AppPath::root() . '/tests/fixtures/tls/ca.pem', $settings->caFile);
        $this->assertFileExists((string) $settings->caFile);
    }

    public function testSmtpSessionSettingsTreatEmptyValuesAsUnset(): void
    {
        putenv('SMTP_CA_FILE=');
        putenv('SMTP_EHLO_NAME=');

        $settings = (new Container($this->unusedConnections()))->smtpSessionSettings();

        $this->assertNull($settings->caFile);
        $this->assertNull($settings->ehloName);
    }

    public function testSmtpSessionSettingsAreMemoized(): void
    {
        $container = new Container($this->unusedConnections());
        $settings = $container->smtpSessionSettings();

        putenv('SMTP_TOTAL_TIMEOUT=99');

        $this->assertSame($settings, $container->smtpSessionSettings());
        $this->assertSame(25.0, $container->smtpSessionSettings()->totalTimeout);
    }

    private function unusedConnections(): ConnectionFactory
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->never())->method('create');
        $factory->expects($this->never())->method('createServer');

        return $factory;
    }

    private function handlerLevel(Logger $logger): Level
    {
        $handlers = $logger->getHandlers();
        $this->assertCount(1, $handlers);
        $this->assertInstanceOf(AbstractHandler::class, $handlers[0]);

        return $handlers[0]->getLevel();
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
