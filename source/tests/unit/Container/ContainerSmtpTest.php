<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Container;

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
use Pidgey\Smtp\HostResolver;
use Pidgey\Smtp\ReplyParser;
use Pidgey\Smtp\SessionSettings;
use Pidgey\Smtp\SmtpTransport;
use Pidgey\Smtp\SystemHostResolver;
use Pidgey\Tests\Support\ContainerTestCase;
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
final class ContainerSmtpTest extends ContainerTestCase
{
    public function testSmtpTransportIsMemoizedAndOpensNoConnection(): void
    {
        $container = new Container($this->unusedConnections());

        $transport = $container->smtpTransport();

        $this->assertInstanceOf(SmtpTransport::class, $transport);
        $this->assertSame($transport, $container->smtpTransport());
    }

    public function testHostPolicyIsMemoized(): void
    {
        $container = new Container($this->unusedConnections());

        $policy = $container->hostPolicy();

        $this->assertSame($policy, $container->hostPolicy());
    }

    public function testHostPolicyDeniesPrivateHostsWhenTheFlagIsUnset(): void
    {
        $this->assertTrue((new Container($this->unusedConnections()))->hostPolicy()->check('127.0.0.1')->isDenied());
    }

    public function testHostPolicyAllowsPrivateHostsWhenTheFlagIsTrue(): void
    {
        putenv('SMTP_ALLOW_PRIVATE_HOSTS=true');

        $check = (new Container($this->unusedConnections()))->hostPolicy()->check('127.0.0.1');

        $this->assertSame('127.0.0.1', $check->ip());
    }

    public function testHostPolicyOnlyTakesTheExactValueTrue(): void
    {
        putenv('SMTP_ALLOW_PRIVATE_HOSTS=TRUE');

        $this->assertTrue((new Container($this->unusedConnections()))->hostPolicy()->check('127.0.0.1')->isDenied());
    }

    public function testHostPolicyCanBeInjected(): void
    {
        $policy = new HostPolicy($this->createStub(HostResolver::class), true);

        $this->assertSame($policy, (new Container($this->unusedConnections(), hostPolicy: $policy))->hostPolicy());
    }

    public function testMimeBuilderIsMemoized(): void
    {
        $container = new Container($this->unusedConnections());

        $builder = $container->mimeBuilder();

        $this->assertSame($builder, $container->mimeBuilder());
    }

    public function testSendFlowIsMemoizedAndOpensNoConnection(): void
    {
        $container = new Container($this->unusedConnections(), logger: new NullLogger());

        $flow = $container->sendFlow();

        $this->assertInstanceOf(SendFlow::class, $flow);
        $this->assertSame($flow, $container->sendFlow());
    }
}
