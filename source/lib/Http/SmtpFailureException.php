<?php

declare(strict_types=1);

namespace Pidgey\Http;

use LogicException;
use Pidgey\Email\SendOutcome;
use Pidgey\Model\SmtpEncryption;
use Pidgey\Smtp\FailureCode;

/**
 * A failed send, as the API error envelope (`email/failures.md#fixed-messages`): the status,
 * the code, the fixed message (with its variant and port hint) and, when the code may carry
 * one, the server's reply as `error.smtp`.
 *
 * The message is always fixed text; the reply text lives only in `smtp()`, and the TLS reason
 * never leaves the log line.
 */
final class SmtpFailureException extends HttpException
{
    private const CONFLICT = 409;
    private const BAD_GATEWAY = 502;
    private const SERVICE_UNAVAILABLE = 503;
    private const GATEWAY_TIMEOUT = 504;

    private const TLS_FAILED = 'TLS negotiation with the SMTP server failed';
    private const TIMED_OUT = 'The SMTP server timed out';

    /** Ports whose TLS failure with `ssl` hints at `tls`. */
    private const STARTTLS_PORTS = [587, 25];

    private const IMPLICIT_TLS_PORT = 465;

    /**
     * Maps a failed outcome onto the exception.
     *
     * @param SendOutcome    $outcome    The failed outcome.
     * @param integer        $port       The connection's port, for the hint.
     * @param SmtpEncryption $encryption The connection's encryption, for the hint.
     *
     * @return self
     *
     * @throws LogicException When the outcome was sent, or its code is unknown.
     */
    public static function fromOutcome(SendOutcome $outcome, int $port, SmtpEncryption $encryption): self
    {
        $code = $outcome->failureCode();

        if ($code === null) {
            throw new LogicException('SmtpFailureException: the outcome was sent');
        }

        [$status, $message, $withReply] = match ($code) {
            SendOutcome::INSECURE_CREDENTIALS => [
                self::CONFLICT,
                'The SMTP connection has credentials but no encryption; set encryption to tls or ssl',
                false,
            ],
            SendOutcome::HOST_NOT_ALLOWED => [
                self::CONFLICT,
                'The SMTP host resolves to an address that is not allowed',
                false,
            ],
            SendOutcome::PASSWORD_UNREADABLE => [
                self::CONFLICT,
                'The stored SMTP password could not be decrypted; set it again',
                false,
            ],
            FailureCode::ConnectionFailed->value => [self::BAD_GATEWAY, 'Could not connect to the SMTP server', false],
            FailureCode::TlsFailed->value => [self::BAD_GATEWAY, self::tlsMessage($port, $encryption), true],
            FailureCode::AuthFailed->value => [self::BAD_GATEWAY, self::authMessage($outcome), true],
            FailureCode::Rejected->value => [self::BAD_GATEWAY, self::rejectedMessage($outcome->step()), true],
            FailureCode::TemporaryFailure->value => [
                self::SERVICE_UNAVAILABLE,
                'The SMTP server is temporarily unavailable; try again later',
                true,
            ],
            FailureCode::ProtocolError->value => [self::BAD_GATEWAY, 'The SMTP server sent an invalid reply', false],
            FailureCode::Timeout->value => [self::GATEWAY_TIMEOUT, self::timeoutMessage($port, $encryption), false],
            default => throw new LogicException('SmtpFailureException: unknown failure code'),
        };

        return new self($status, $code, $message, null, $withReply ? self::replyOf($outcome) : null);
    }

    /**
     * Returns the `error.smtp` object, or null when the outcome has no reply.
     *
     * @param SendOutcome $outcome The outcome.
     *
     * @return array{code: int, message: string}|null
     */
    private static function replyOf(SendOutcome $outcome): ?array
    {
        $code = $outcome->replyCode();
        $message = $outcome->replyMessage();

        if ($code === null || $message === null) {
            return null;
        }

        return ['code' => $code, 'message' => $message];
    }

    /**
     * Returns the `SMTP_TLS_FAILED` message, with the hint for `ssl` on 587 or 25.
     *
     * @param integer        $port       The port.
     * @param SmtpEncryption $encryption The encryption.
     *
     * @return string
     */
    private static function tlsMessage(int $port, SmtpEncryption $encryption): string
    {
        if ($encryption === SmtpEncryption::Ssl && in_array($port, self::STARTTLS_PORTS, true)) {
            return self::TLS_FAILED . "; port {$port} usually uses encryption tls";
        }

        return self::TLS_FAILED;
    }

    /**
     * Returns the `SMTP_TIMEOUT` message, with the hint for `tls` or `none` on 465.
     *
     * @param integer        $port       The port.
     * @param SmtpEncryption $encryption The encryption.
     *
     * @return string
     */
    private static function timeoutMessage(int $port, SmtpEncryption $encryption): string
    {
        if ($port === self::IMPLICIT_TLS_PORT && $encryption !== SmtpEncryption::Ssl) {
            return self::TIMED_OUT . '; port 465 usually requires encryption ssl';
        }

        return self::TIMED_OUT;
    }

    /**
     * Returns the `SMTP_AUTH_FAILED` variant: `530` at `MAIL FROM` requires authentication, no
     * reply means no supported mechanism, any other reply rejected the credentials.
     *
     * @param SendOutcome $outcome The outcome.
     *
     * @return string
     */
    private static function authMessage(SendOutcome $outcome): string
    {
        if ($outcome->step() === SendOutcome::STEP_MAIL_FROM) {
            return 'The SMTP server requires authentication';
        }

        if ($outcome->replyCode() === null) {
            return 'The SMTP server offers no supported authentication mechanism';
        }

        return 'The SMTP server rejected the credentials';
    }

    /**
     * Returns the `SMTP_REJECTED` message of the failing step.
     *
     * @param string|null $step The step.
     *
     * @return string
     */
    private static function rejectedMessage(?string $step): string
    {
        return match ($step) {
            SendOutcome::STEP_MAIL_FROM => 'The SMTP server rejected the sender',
            SendOutcome::STEP_RCPT_TO => 'The SMTP server rejected the recipient',
            SendOutcome::STEP_DATA, SendOutcome::STEP_END_OF_DATA => 'The SMTP server rejected the message',
            default => 'The SMTP server rejected the connection',
        };
    }
}
