<?php

declare(strict_types=1);

namespace Pidgey\Email;

/**
 * The outcome of a send: sent, with the server's reply to the final `.`, or failed, with the
 * failure code, the step and, when one was received, the reply.
 *
 * The reply message is already redacted and sanitized. The TLS reason is for the log line
 * only and never goes into a response.
 */
final readonly class SendOutcome
{
    /** The connection has credentials but no encryption. */
    public const INSECURE_CREDENTIALS = 'SMTP_INSECURE_CREDENTIALS';

    /** The host resolves to (or is) a denied address. */
    public const HOST_NOT_ALLOWED = 'SMTP_HOST_NOT_ALLOWED';

    /** The stored password could not be decrypted. */
    public const PASSWORD_UNREADABLE = 'SMTP_PASSWORD_UNREADABLE';

    /** The step of the three send-time failures. */
    public const STEP_SEND_TIME_CHECK = 'send-time-check';

    public const STEP_CONNECT = 'connect';
    public const STEP_GREETING = 'greeting';
    public const STEP_EHLO = 'ehlo';
    public const STEP_STARTTLS = 'starttls';
    public const STEP_AUTH = 'auth';
    public const STEP_MAIL_FROM = 'mail-from';
    public const STEP_RCPT_TO = 'rcpt-to';
    public const STEP_DATA = 'data';
    public const STEP_END_OF_DATA = 'end-of-data';

    /**
     * Builds the outcome; use the named constructors.
     *
     * @param string|null  $failureCode  The failure code, or null when sent.
     * @param string|null  $step         The failing step, or null.
     * @param integer|null $replyCode    The reply code, or null.
     * @param string|null  $replyMessage The cleaned reply text, or null.
     * @param string|null  $tlsReason    The OpenSSL reason of a TLS failure, or null.
     */
    private function __construct(
        private ?string $failureCode,
        private ?string $step,
        private ?int $replyCode,
        private ?string $replyMessage,
        private ?string $tlsReason,
    ) {
    }

    /**
     * The message was sent.
     *
     * @param integer $code    The code of the reply to the final `.`.
     * @param string  $message The cleaned reply text.
     *
     * @return self
     */
    public static function sent(int $code, string $message): self
    {
        return new self(null, null, $code, $message, null);
    }

    /**
     * The send failed.
     *
     * @param string       $failureCode  One of the seven transport codes or the three
     *                                   send-time codes.
     * @param string|null  $step         The failing step.
     * @param integer|null $replyCode    The reply code, when a reply was received.
     * @param string|null  $replyMessage The cleaned reply text, when a reply was received.
     * @param string|null  $tlsReason    The OpenSSL reason of a TLS failure.
     *
     * @return self
     */
    public static function failed(
        string $failureCode,
        ?string $step,
        ?int $replyCode,
        ?string $replyMessage,
        ?string $tlsReason,
    ): self {
        return new self($failureCode, $step, $replyCode, $replyMessage, $tlsReason);
    }

    /**
     * Tells whether the message was sent.
     *
     * @return boolean
     */
    public function isSent(): bool
    {
        return $this->failureCode === null;
    }

    /**
     * Returns the failure code, or null when sent.
     *
     * @return string|null
     */
    public function failureCode(): ?string
    {
        return $this->failureCode;
    }

    /**
     * Returns the failing step, or null.
     *
     * @return string|null
     */
    public function step(): ?string
    {
        return $this->step;
    }

    /**
     * Returns the reply code (of the final `.` when sent), or null.
     *
     * @return integer|null
     */
    public function replyCode(): ?int
    {
        return $this->replyCode;
    }

    /**
     * Returns the redacted and sanitized reply text, or null.
     *
     * @return string|null
     */
    public function replyMessage(): ?string
    {
        return $this->replyMessage;
    }

    /**
     * Returns the OpenSSL reason of a TLS failure, for the log only.
     *
     * @return string|null
     */
    public function tlsReason(): ?string
    {
        return $this->tlsReason;
    }
}
