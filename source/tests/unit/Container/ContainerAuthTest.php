<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Container;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Auth\AccessToken;
use Pidgey\Auth\AccessTokenClaims;
use Pidgey\Auth\AccountEdit;
use Pidgey\Auth\ApiTokens;
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
use Pidgey\Email\MimeBuilder;
use Pidgey\Email\SendFlow;
use Pidgey\Http\OperatorLog;
use Pidgey\Infra\AppPath;
use Pidgey\Infra\ConnectionFactory;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\LoggerFactory;
use Pidgey\Infra\SystemClock;
use Pidgey\Infra\SystemWallClock;
use Pidgey\Infra\Transaction;
use Pidgey\Infra\UserApproval;
use Pidgey\Repository\AccountEditLockoutRepository;
use Pidgey\Repository\ApiTokenRepository;
use Pidgey\Repository\AuthorizationRequestRepository;
use Pidgey\Repository\OrganizationRepository;
use Pidgey\Repository\PasswordResetTokenRepository;
use Pidgey\Repository\RefreshTokenRepository;
use Pidgey\Repository\SmtpConnectionRepository;
use Pidgey\Repository\UserRepository;
use Pidgey\Smtp\HostCheck;
use Pidgey\Smtp\HostPolicy;
use Pidgey\Smtp\ReplyParser;
use Pidgey\Smtp\SessionSettings;
use Pidgey\Smtp\SmtpTransport;
use Pidgey\Smtp\SystemHostResolver;
use Pidgey\Tests\Support\ContainerTestCase;
use Pidgey\Tests\Support\FrozenWallClock;
use RuntimeException;

#[CoversClass(Container::class)]
#[UsesClass(AccessToken::class)]
#[UsesClass(AccessTokenClaims::class)]
#[UsesClass(AccountEdit::class)]
#[UsesClass(ApiTokens::class)]
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
#[UsesClass(OperatorLog::class)]
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
#[UsesClass(SmtpTransport::class)]
#[UsesClass(HostPolicy::class)]
#[UsesClass(MimeBuilder::class)]
#[UsesClass(SendFlow::class)]
#[UsesClass(HostCheck::class)]
#[UsesClass(SystemHostResolver::class)]
#[UsesClass(ReplyParser::class)]
final class ContainerAuthTest extends ContainerTestCase
{
    public function testAccessTokenIsMemoizedAndOpensNoConnection(): void
    {
        $container = new Container($this->unusedConnections());

        $accessToken = $container->accessToken();

        $this->assertInstanceOf(AccessToken::class, $accessToken);
        $this->assertSame($accessToken, $container->accessToken());
    }

    public function testAccessTokenUsesTheSecretKeyTheDefaultTtlAndTheContainerClock(): void
    {
        $container = new Container($this->unusedConnections(), new FrozenWallClock(1_800_000_000));

        $token = $container->accessToken()->issue(7, '0b8f2c3e-1d4a-4b6c-9e7f-a1b2c3d4e5f6', 'alice', false);
        $claims = (new AccessToken(new SecretKeys(self::SECRET_KEY), 900, new FrozenWallClock(1_800_000_000)))
            ->verify($token);

        $this->assertNotNull($claims);
        $this->assertSame(1_800_000_000, $claims->issuedAt);
        $this->assertSame(1_800_000_900, $claims->expiresAt);
    }

    public function testAccessTokenReadsTheTtlFromTheEnvironment(): void
    {
        putenv('ACCESS_TOKEN_TTL=60');
        $container = new Container($this->unusedConnections(), new FrozenWallClock(1_800_000_000));

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

    public function testApiTokensIsMemoizedOnASinglePdo(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($this->createStub(PDO::class));
        $container = new Container($factory);

        $apiTokens = $container->apiTokens();

        $this->assertInstanceOf(ApiTokens::class, $apiTokens);
        $this->assertSame($apiTokens, $container->apiTokens());
        $container->apiTokenRepository();
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
}
