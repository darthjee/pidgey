<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Email;

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
use Pidgey\Model\SmtpEncryption;
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
final class SendFlowCredentialsTest extends SendFlowTestCase
{
    public function testSendsWithoutCredentialsWhenNoneAreStored(): void
    {
        $connection = $this->connection(encryption: SmtpEncryption::None, username: null, password: null);

        $outcome = $this->flow($this->ok())->send($connection, $this->message(), SendActor::user(5));

        $this->assertTrue($outcome->isSent());
        $this->assertNull($this->calls[0]['credentials']);
    }

    public function testSendsWithoutCredentialsWhenOnlyAUsernameIsStored(): void
    {
        $connection = $this->connection(encryption: SmtpEncryption::None, password: null);

        $this->flow($this->ok())->send($connection, $this->message(), SendActor::user(5));

        $this->assertNull($this->calls[0]['credentials']);
    }

    public function testDecryptsThePasswordForTheTransport(): void
    {
        $seen = null;
        $flow = $this->flow(static function (?Credentials $credentials) use (&$seen): SmtpResult {
            $seen = $credentials?->password()->reveal();

            return new SmtpResult(new Reply(250, ['Ok']));
        });

        $flow->send($this->connection(), $this->message(), SendActor::user(5));

        $this->assertSame(self::PASSWORD, $seen);
    }

    public function testACorruptPasswordIsUnreadable(): void
    {
        $outcome = $this->flow($this->ok())
            ->send($this->connection(password: 'garbage'), $this->message(), SendActor::user(5));

        $this->assertSendTimeFailure('SMTP_PASSWORD_UNREADABLE', $outcome);
    }

    public function testAPasswordUnderAnUnknownKeyIsUnreadable(): void
    {
        $stored = (new PasswordCipher(new EncryptionKeys(self::OTHER_KEY)))
            ->encrypt(self::PASSWORD, self::ORGANIZATION, self::CONNECTION);

        $outcome = $this->flow($this->ok())
            ->send($this->connection(password: $stored), $this->message(), SendActor::user(5));

        $this->assertSendTimeFailure('SMTP_PASSWORD_UNREADABLE', $outcome);
    }

    public function testAPasswordBoundToAnotherRowIsUnreadable(): void
    {
        $stored = $this->cipher()->encrypt(self::PASSWORD, self::ORGANIZATION, self::CONNECTION + 1);

        $outcome = $this->flow($this->ok())
            ->send($this->connection(password: $stored), $this->message(), SendActor::user(5));

        $this->assertSendTimeFailure('SMTP_PASSWORD_UNREADABLE', $outcome);
    }
}
