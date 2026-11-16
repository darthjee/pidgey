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
use Pidgey\Smtp\ReplyParser;
use Pidgey\Smtp\SessionSettings;
use Pidgey\Smtp\SmtpTransport;
use Pidgey\Smtp\SystemHostResolver;
use Pidgey\Tests\Support\ContainerTestCase;
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
final class ContainerSmtpSettingsTest extends ContainerTestCase
{
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
}
