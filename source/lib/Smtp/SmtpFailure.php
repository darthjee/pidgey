<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

use RuntimeException;
use Throwable;

/**
 * A classified SMTP session failure: its code, the step it happened at, and the raw reply
 * when one was received.
 *
 * The message is fixed text (`SMTP <step>: <code>`) and never carries a line sent or
 * received; a TLS failure may carry the OpenSSL reason, for the log line only.
 */
final class SmtpFailure extends RuntimeException
{
    private readonly ?string $reason;

    /**
     * Builds the failure.
     *
     * @param FailureCode    $failureCode The classified failure.
     * @param SmtpStep       $step        The step it happened at.
     * @param Reply|null     $reply       The raw reply received, or null.
     * @param string|null    $reason      OpenSSL reason; kept for `SMTP_TLS_FAILED` only.
     * @param Throwable|null $previous    The underlying stream or parser failure, if any.
     */
    public function __construct(
        private readonly FailureCode $failureCode,
        private readonly SmtpStep $step,
        private readonly ?Reply $reply = null,
        ?string $reason = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(sprintf('SMTP %s: %s', $step->name, $failureCode->value), 0, $previous);
        $this->reason = $failureCode === FailureCode::TlsFailed ? $reason : null;
    }

    /**
     * Returns the classified failure.
     *
     * @return FailureCode
     */
    public function failureCode(): FailureCode
    {
        return $this->failureCode;
    }

    /**
     * Returns the step the failure happened at.
     *
     * @return SmtpStep
     */
    public function step(): SmtpStep
    {
        return $this->step;
    }

    /**
     * Returns the raw reply received, or null when there was none.
     *
     * @return Reply|null
     */
    public function reply(): ?Reply
    {
        return $this->reply;
    }

    /**
     * Returns the OpenSSL reason of a TLS failure, or null.
     *
     * @return string|null
     */
    public function reason(): ?string
    {
        return $this->reason;
    }
}
