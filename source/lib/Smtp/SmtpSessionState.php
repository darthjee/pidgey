<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

/**
 * The state of one `SmtpTransport::send()` call: its stream, its session deadline, its
 * settings and its target. Internal to the transport.
 *
 * @internal
 */
final readonly class SmtpSessionState
{
    /**
     * Builds the state of one session.
     *
     * @param SmtpStream      $stream   The session's stream.
     * @param Deadline        $deadline The session deadline (total timeout, from connect time).
     * @param SessionSettings $settings Timeouts, CA bundle and `EHLO` name.
     * @param Target          $target   Where the session connects.
     */
    public function __construct(
        public SmtpStream $stream,
        public Deadline $deadline,
        public SessionSettings $settings,
        public Target $target,
    ) {
    }

    /**
     * Returns a fresh budget for one command's write or reply: `min(command timeout, time
     * remaining)`.
     *
     * @return Deadline
     */
    public function commandBudget(): Deadline
    {
        return $this->deadline->within($this->settings->commandTimeout);
    }
}
