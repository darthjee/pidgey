<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Email;

use LogicException;
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
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\HttpException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\Response;
use Pidgey\Http\SmtpFailureException;
use Pidgey\Infra\AppPath;
use Pidgey\Infra\Env;
use Pidgey\Infra\LoggerFactory;
use Pidgey\Infra\SecretString;
use Pidgey\Model\SmtpConnection;
use Pidgey\Model\SmtpEncryption;
use Pidgey\Smtp\Credentials;
use Pidgey\Smtp\Deadline;
use Pidgey\Smtp\EhloExtensions;
use Pidgey\Smtp\Envelope;
use Pidgey\Smtp\HostCheck;
use Pidgey\Smtp\HostPolicy;
use Pidgey\Smtp\MalformedReply;
use Pidgey\Smtp\Reply;
use Pidgey\Smtp\ReplyParser;
use Pidgey\Smtp\SessionSettings;
use Pidgey\Smtp\SmtpFailure;
use Pidgey\Smtp\SmtpResult;
use Pidgey\Smtp\SmtpSessionState;
use Pidgey\Smtp\SmtpTransport;
use Pidgey\Smtp\SocketStream;
use Pidgey\Smtp\StreamFailure;
use Pidgey\Smtp\SystemHostResolver;
use Pidgey\Smtp\Target;
use Pidgey\Smtp\TlsContext;
use Pidgey\Tests\Support\SendFlowIntegrationTestCase;

#[CoversClass(SendFlow::class)]
#[CoversClass(SmtpFailureException::class)]
#[UsesClass(AppPath::class)]
#[UsesClass(Credentials::class)]
#[UsesClass(Deadline::class)]
#[UsesClass(DecryptionFailed::class)]
#[UsesClass(EhloExtensions::class)]
#[UsesClass(EncryptionKeys::class)]
#[UsesClass(Env::class)]
#[UsesClass(Envelope::class)]
#[UsesClass(ErrorFormatter::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(HostCheck::class)]
#[UsesClass(HostPolicy::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(LoggerFactory::class)]
#[UsesClass(MalformedReply::class)]
#[UsesClass(Message::class)]
#[UsesClass(MimeBuilder::class)]
#[UsesClass(PasswordCipher::class)]
#[UsesClass(Reply::class)]
#[UsesClass(ReplyParser::class)]
#[UsesClass(ReplyText::class)]
#[UsesClass(Response::class)]
#[UsesClass(SecretString::class)]
#[UsesClass(SendActor::class)]
#[UsesClass(SendOutcome::class)]
#[UsesClass(SessionSettings::class)]
#[UsesClass(SmtpConnection::class)]
#[UsesClass(SmtpFailure::class)]
#[UsesClass(SmtpResult::class)]
#[UsesClass(SmtpSessionState::class)]
#[UsesClass(SmtpTransport::class)]
#[UsesClass(SocketStream::class)]
#[UsesClass(StreamFailure::class)]
#[UsesClass(SystemHostResolver::class)]
#[UsesClass(Target::class)]
#[UsesClass(TlsContext::class)]
final class SendFlowPolicyTest extends SendFlowIntegrationTestCase
{
    public function testAPrivateHostIsNotAllowed(): void
    {
        [$listener, $port] = $this->listener();
        $connection = $this->connection('127.0.0.1', $port, SmtpEncryption::Ssl, self::USERNAME);

        $outcome = $this->flow($this->fakeSettings(), false)->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame(
            $this->body(409, 'SMTP_HOST_NOT_ALLOWED', 'The SMTP host resolves to an address that is not allowed'),
            $this->formatted($outcome, $connection),
        );
        $this->assertNotContacted($listener);
        $this->assertNoCanary($outcome, $connection);
    }

    public function testAPrivateHostNameIsNotAllowed(): void
    {
        [$listener, $port] = $this->listener();
        $connection = $this->connection('localhost', $port, SmtpEncryption::Ssl, self::USERNAME);

        $outcome = $this->flow($this->fakeSettings(), false)->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame('SMTP_HOST_NOT_ALLOWED', $outcome->failureCode());
        $this->assertNotContacted($listener);
    }

    public function testInsecureCredentialsAreRefused(): void
    {
        [$listener, $port] = $this->listener();
        $connection = $this->connection('localhost', $port, SmtpEncryption::None, self::USERNAME);

        $outcome = $this->flow($this->fakeSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame(
            $this->body(
                409,
                'SMTP_INSECURE_CREDENTIALS',
                'The SMTP connection has credentials but no encryption; set encryption to tls or ssl',
            ),
            $this->formatted($outcome, $connection),
        );
        $this->assertNotContacted($listener);
        $this->assertNoCanary($outcome, $connection);
    }

    public function testAnUnreadablePasswordIsRefused(): void
    {
        [$listener, $port] = $this->listener();
        $stored = (new PasswordCipher(new EncryptionKeys(self::KEY . '-other')))
            ->encrypt(self::CANARY, self::ORGANIZATION, self::CONNECTION);
        $connection = $this->connection('localhost', $port, SmtpEncryption::Ssl, self::USERNAME, stored: $stored);

        $outcome = $this->flow($this->fakeSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame(
            $this->body(
                409,
                'SMTP_PASSWORD_UNREADABLE',
                'The stored SMTP password could not be decrypted; set it again',
            ),
            $this->formatted($outcome, $connection),
        );
        $this->assertNotContacted($listener);
        $this->assertNoCanary($outcome, $connection);
    }

    public function testASentOutcomeIsNotAnError(): void
    {
        $this->expectException(LogicException::class);

        SmtpFailureException::fromOutcome(SendOutcome::sent(250, 'ok'), 25, SmtpEncryption::None);
    }
}
