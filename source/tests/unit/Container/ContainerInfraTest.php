<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Container;

use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
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
use Pidgey\Email\MimeBuilder;
use Pidgey\Email\SendFlow;
use Pidgey\Http\OperatorLog;
use Pidgey\Infra\AppPath;
use Pidgey\Infra\Clock;
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
use Psr\Log\NullLogger;

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
final class ContainerInfraTest extends ContainerTestCase
{
    public function testWallClockDefaultsToTheSystemClockAndIsMemoized(): void
    {
        $container = new Container($this->unusedConnections());

        $clock = $container->wallClock();

        $this->assertInstanceOf(SystemWallClock::class, $clock);
        $this->assertSame($clock, $container->wallClock());
    }

    public function testWallClockCanBeInjected(): void
    {
        $clock = new FrozenWallClock(1_800_000_000);

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

    public function testOperatorLogIsMemoizedAndOpensNoConnection(): void
    {
        $container = new Container($this->unusedConnections(), logger: new NullLogger());

        $operatorLog = $container->operatorLog();

        $this->assertInstanceOf(OperatorLog::class, $operatorLog);
        $this->assertSame($operatorLog, $container->operatorLog());
    }
}
