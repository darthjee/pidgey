<?php

declare(strict_types=1);

namespace Pidgey\Email;

use Pidgey\Crypto\DecryptionFailed;
use Pidgey\Crypto\PasswordCipher;
use Pidgey\Infra\SecretString;
use Pidgey\Model\SmtpConnection;
use Pidgey\Model\SmtpEncryption;
use Pidgey\Smtp\Credentials;
use Pidgey\Smtp\Envelope;
use Pidgey\Smtp\FailureCode;
use Pidgey\Smtp\HostPolicy;
use Pidgey\Smtp\SessionSettings;
use Pidgey\Smtp\SmtpFailure;
use Pidgey\Smtp\SmtpStep;
use Pidgey\Smtp\Target;
use Pidgey\Smtp\TlsMode;
use Pidgey\Smtp\Transport;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use SodiumException;

/**
 * The send flow shared by the test email and `POST /v1/messages` (`email/send-flow.md`).
 *
 * 1. Credentials with `encryption: none` are `SMTP_INSECURE_CREDENTIALS`.
 * 2. The host check: a denied address is `SMTP_HOST_NOT_ALLOWED`, no address is
 *    `SMTP_CONNECTION_FAILED`.
 * 3. The stored password is decrypted into a `SecretString`; any failure is
 *    `SMTP_PASSWORD_UNREADABLE`.
 * 4. The MIME message is built and the transport runs against the checked IP, `host` staying
 *    the TLS peer name.
 * 5. The reply is redacted and sanitized, the password wiped, and one `smtp-send-attempt`
 *    line logged.
 *
 * SMTP and send-time failures are outcomes, never exceptions; anything else propagates.
 */
final class SendFlow
{
    private const LOG_MESSAGE = 'smtp-send-attempt';

    /**
     * Builds the send flow.
     *
     * @param HostPolicy      $hostPolicy The send-time host check.
     * @param PasswordCipher  $cipher     Decrypts the stored password.
     * @param MimeBuilder     $mime       Builds the message.
     * @param Transport       $transport  Runs the SMTP session.
     * @param SessionSettings $settings   Timeouts, CA bundle and `EHLO` name.
     * @param LoggerInterface $logger     Receives the attempt log line.
     */
    public function __construct(
        private readonly HostPolicy $hostPolicy,
        private readonly PasswordCipher $cipher,
        private readonly MimeBuilder $mime,
        private readonly Transport $transport,
        private readonly SessionSettings $settings,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Sends one message over the connection and logs the attempt.
     *
     * @param SmtpConnection $connection The loaded connection.
     * @param Message        $message    The message.
     * @param SendActor      $actor      Who asked for the send.
     *
     * @return SendOutcome
     */
    public function send(SmtpConnection $connection, Message $message, SendActor $actor): SendOutcome
    {
        $outcome = $this->attempt($connection, $message);

        $this->log($connection, $message, $actor, $outcome);

        return $outcome;
    }

    /**
     * Runs the send-time checks, then the session.
     *
     * @param SmtpConnection $connection The connection.
     * @param Message        $message    The message.
     *
     * @return SendOutcome
     */
    private function attempt(SmtpConnection $connection, Message $message): SendOutcome
    {
        $hasCredentials = $connection->username !== null && $connection->encryptedPassword !== null;

        if ($hasCredentials && $connection->encryption === SmtpEncryption::None) {
            return self::sendTimeFailure(SendOutcome::INSECURE_CREDENTIALS);
        }

        $check = $this->hostPolicy->check($connection->host);

        if ($check->isDenied()) {
            return self::sendTimeFailure(SendOutcome::HOST_NOT_ALLOWED);
        }

        if (!$check->isAllowed()) {
            $code = FailureCode::ConnectionFailed->value;

            return SendOutcome::failed($code, SendOutcome::STEP_CONNECT, null, null, null);
        }

        $password = $hasCredentials ? $this->decrypt($connection) : null;

        if ($hasCredentials && $password === null) {
            return self::sendTimeFailure(SendOutcome::PASSWORD_UNREADABLE);
        }

        try {
            return $this->deliver($connection, $message, $check->ip(), $password);
        } finally {
            $password?->wipe();
        }
    }

    /**
     * Decrypts the stored password straight into a `SecretString`.
     *
     * @param SmtpConnection $connection The connection, with a stored password.
     *
     * @return SecretString|null The password, or null when it cannot be decrypted.
     */
    private function decrypt(SmtpConnection $connection): ?SecretString
    {
        try {
            return new SecretString($this->cipher->decrypt(
                (string) $connection->encryptedPassword,
                $connection->organizationId,
                $connection->id,
            ));
        } catch (DecryptionFailed | SodiumException) {
            return null;
        }
    }

    /**
     * Builds the message, runs the session against the checked IP and maps its result.
     *
     * @param SmtpConnection    $connection The connection.
     * @param Message           $message    The message.
     * @param string            $address    The checked IP.
     * @param SecretString|null $password   The decrypted password, or null without credentials.
     *
     * @return SendOutcome
     */
    private function deliver(
        SmtpConnection $connection,
        Message $message,
        string $address,
        #[SensitiveParameter] ?SecretString $password,
    ): SendOutcome {
        $username = $password === null ? null : $connection->username;
        $credentials = $password === null ? null : new Credentials((string) $username, $password);
        $target = new Target($connection->host, $address, $connection->port, self::tlsMode($connection->encryption));

        try {
            $result = $this->transport->send(
                $target,
                $this->settings,
                $credentials,
                new Envelope($connection->fromAddress, [$message->to]),
                $this->mime->build($connection->fromAddress, $message),
            );
        } catch (SmtpFailure $failure) {
            $reply = $failure->reply();

            return SendOutcome::failed(
                $failure->failureCode()->value,
                self::stepName($failure->step()),
                $reply?->code(),
                $reply === null ? null : ReplyText::clean($reply->text(), $password, $username),
                $failure->reason(),
            );
        }

        return SendOutcome::sent($result->code(), ReplyText::clean($result->reply()->text(), $password, $username));
    }

    /**
     * Writes the attempt log line: `info` when sent, `warning` when failed; absent fields are
     * omitted, and never the subject, the body or the credentials.
     *
     * @param SmtpConnection $connection The connection.
     * @param Message        $message    The message.
     * @param SendActor      $actor      Who asked for the send.
     * @param SendOutcome    $outcome    The outcome.
     *
     * @return void
     */
    private function log(SmtpConnection $connection, Message $message, SendActor $actor, SendOutcome $outcome): void
    {
        $context = array_filter(
            [
                'organizationId' => $connection->organizationId,
                'connectionId' => $connection->id,
                ...$actor->logContext(),
                'recipient' => $message->to,
                'outcome' => $outcome->failureCode() ?? 'sent',
                'step' => $outcome->step(),
                'replyCode' => $outcome->replyCode(),
                'reply' => $outcome->replyMessage(),
                'tlsReason' => $outcome->tlsReason(),
            ],
            static fn (mixed $value): bool => $value !== null,
        );

        if ($outcome->isSent()) {
            $this->logger->info(self::LOG_MESSAGE, $context);

            return;
        }

        $this->logger->warning(self::LOG_MESSAGE, $context);
    }

    /**
     * Returns a send-time failure: step `send-time-check`, no reply.
     *
     * @param string $failureCode The send-time code.
     *
     * @return SendOutcome
     */
    private static function sendTimeFailure(string $failureCode): SendOutcome
    {
        return SendOutcome::failed($failureCode, SendOutcome::STEP_SEND_TIME_CHECK, null, null, null);
    }

    /**
     * Maps the connection's encryption onto the transport's TLS mode.
     *
     * @param SmtpEncryption $encryption The encryption.
     *
     * @return TlsMode
     */
    private static function tlsMode(SmtpEncryption $encryption): TlsMode
    {
        return match ($encryption) {
            SmtpEncryption::None => TlsMode::Plain,
            SmtpEncryption::Tls => TlsMode::StartTls,
            SmtpEncryption::Ssl => TlsMode::Implicit,
        };
    }

    /**
     * Returns the outcome step name of a session step.
     *
     * @param SmtpStep $step The step.
     *
     * @return string
     */
    private static function stepName(SmtpStep $step): string
    {
        return match ($step) {
            SmtpStep::Connect => SendOutcome::STEP_CONNECT,
            SmtpStep::Greeting => SendOutcome::STEP_GREETING,
            SmtpStep::Ehlo => SendOutcome::STEP_EHLO,
            SmtpStep::StartTls => SendOutcome::STEP_STARTTLS,
            SmtpStep::Auth => SendOutcome::STEP_AUTH,
            SmtpStep::MailFrom => SendOutcome::STEP_MAIL_FROM,
            SmtpStep::RcptTo => SendOutcome::STEP_RCPT_TO,
            SmtpStep::Data => SendOutcome::STEP_DATA,
            SmtpStep::EndOfData => SendOutcome::STEP_END_OF_DATA,
        };
    }
}
