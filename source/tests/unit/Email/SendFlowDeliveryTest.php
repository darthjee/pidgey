<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Email;

use PHPUnit\Framework\Attributes\DataProvider;
use Pidgey\Model\SmtpEncryption;
use Pidgey\Smtp\TlsMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Crypto\DecryptionFailed;
use Pidgey\Crypto\EncryptionKeys;
use Pidgey\Crypto\PasswordCipher;
use Pidgey\Email\Message;
use Pidgey\Email\MimeBuilder;
use Pidgey\Email\ReplyText;
use Pidgey\Email\SendActor;
use Pidgey\Email\SendFlow;
use Pidgey\Email\SendOutcome;
use Pidgey\Infra\LoggerFactory;
use Pidgey\Infra\SecretString;
use Pidgey\Model\SmtpConnection;
use Pidgey\Smtp\Credentials;
use Pidgey\Smtp\Envelope;
use Pidgey\Smtp\HostCheck;
use Pidgey\Smtp\HostPolicy;
use Pidgey\Smtp\Reply;
use Pidgey\Smtp\SessionSettings;
use Pidgey\Smtp\SmtpFailure;
use Pidgey\Smtp\SmtpResult;
use Pidgey\Smtp\Target;
use Pidgey\Tests\Support\SendFlowTestCase;

#[CoversClass(SendFlow::class)]
#[CoversClass(SendOutcome::class)]
#[CoversClass(SendActor::class)]
#[UsesClass(ReplyText::class)]
#[UsesClass(Message::class)]
#[UsesClass(MimeBuilder::class)]
#[UsesClass(HostPolicy::class)]
#[UsesClass(HostCheck::class)]
#[UsesClass(PasswordCipher::class)]
#[UsesClass(EncryptionKeys::class)]
#[UsesClass(DecryptionFailed::class)]
#[UsesClass(SecretString::class)]
#[UsesClass(Credentials::class)]
#[UsesClass(Envelope::class)]
#[UsesClass(Target::class)]
#[UsesClass(SessionSettings::class)]
#[UsesClass(SmtpFailure::class)]
#[UsesClass(SmtpResult::class)]
#[UsesClass(Reply::class)]
#[UsesClass(SmtpConnection::class)]
#[UsesClass(LoggerFactory::class)]
final class SendFlowDeliveryTest extends SendFlowTestCase
{
    public function testSendsAndReturnsTheCleanedReply(): void
    {
        $flow = $this->flow(static fn (): SmtpResult => new SmtpResult(new Reply(250, ['2.0.0 Ok:  queued'])));

        $outcome = $flow->send($this->connection(), $this->message(), SendActor::user(5));

        $this->assertTrue($outcome->isSent());
        $this->assertNull($outcome->failureCode());
        $this->assertNull($outcome->step());
        $this->assertSame(250, $outcome->replyCode());
        $this->assertSame('2.0.0 Ok: queued', $outcome->replyMessage());
        $this->assertNull($outcome->tlsReason());
    }

    public function testPassesTheCheckedIpHostPortModeEnvelopeAndMime(): void
    {
        $flow = $this->flow($this->ok(), ['2001:db8::1', self::PUBLIC_IP]);

        $connection = $this->connection(port: 2525, encryption: SmtpEncryption::Ssl);

        $flow->send($connection, $this->message(), SendActor::user(5));

        $this->assertCount(1, $this->calls);
        $call = $this->calls[0];
        $this->assertSame(self::HOST, $call['target']->host);
        $this->assertSame(self::PUBLIC_IP, $call['target']->address);
        $this->assertSame(2525, $call['target']->port);
        $this->assertSame(TlsMode::Implicit, $call['target']->tlsMode);
        $this->assertSame('sender@example.com', $call['envelope']->from);
        $this->assertSame([self::RECIPIENT], $call['envelope']->recipients);
        $this->assertStringContainsString("From: sender@example.com\r\nTo: " . self::RECIPIENT, $call['message']);
        $this->assertStringContainsString('Subject: ' . self::SUBJECT, $call['message']);
        $this->assertSame(self::USERNAME, $call['credentials']?->username());
        $this->assertSame('pidgey.test', $call['settings']->ehloName);
    }

    /**
     * @return iterable<string, array{0: SmtpEncryption, 1: TlsMode}>
     */
    public static function encryptions(): iterable
    {
        yield 'none' => [SmtpEncryption::None, TlsMode::Plain];
        yield 'tls' => [SmtpEncryption::Tls, TlsMode::StartTls];
        yield 'ssl' => [SmtpEncryption::Ssl, TlsMode::Implicit];
    }

    #[DataProvider('encryptions')]
    public function testMapsTheEncryptionToTheTlsMode(SmtpEncryption $encryption, TlsMode $mode): void
    {
        $connection = $this->connection(encryption: $encryption, username: null, password: null);

        $this->flow($this->ok())->send($connection, $this->message(), SendActor::user(5));

        $this->assertSame($mode, $this->calls[0]['target']->tlsMode);
    }
}
