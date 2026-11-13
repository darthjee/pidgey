<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

use Closure;
use Pidgey\Infra\Clock;
use SensitiveParameter;

/**
 * The SMTP session (`email/send-flow.md#smtp-session`): one command, then its reply, then the
 * next (no `PIPELINING`), over a fresh `SmtpStream` per `send()`.
 *
 * - The session deadline starts from the total timeout at connect time; `open()` and the
 *   greeting share the connect timeout, every other reply (and the `STARTTLS` handshake) gets
 *   `min(command timeout, time remaining)`, and so does every write.
 * - `STARTTLS` is never downgraded; `AUTH` uses `PLAIN`, else `LOGIN`, and is never retried.
 * - The message is split on CRLF, bare CR and bare LF, rewritten with CRLF and dot-stuffed.
 * - Failures are `SmtpFailure`s classified per `email/failures.md#classification-rules`;
 *   their messages are fixed text. Nothing is logged; the stream is always closed.
 *
 * Reply classification, the step aside: a 5xx reply is `SMTP_REJECTED`, a 4xx reply is
 * `SMTP_TEMPORARY_FAILURE`. A reply that is neither expected nor an error (e.g. `250` to
 * `DATA`, or a `2xx` greeting other than `220`) breaks the protocol: `SMTP_PROTOCOL_ERROR`.
 */
final class SmtpTransport implements Transport
{
    private const GREETING_OK = 220;
    private const EHLO_OK = 250;
    private const STARTTLS_OK = 220;
    private const AUTH_OK = 235;
    private const AUTH_CONTINUE = 334;
    private const MAIL_OK = 250;
    private const RCPT_OK = [250, 251];
    private const DATA_OK = 354;
    private const AUTH_REQUIRED = 530;

    private const PERMANENT = 500;
    private const TRANSIENT = 400;
    private const SUCCESS_CLASS = 2;

    private const PLAIN = 'PLAIN';
    private const LOGIN = 'LOGIN';

    /** @var Closure(): SmtpStream */
    private readonly Closure $streamFactory;

    private readonly ReplyParser $parser;

    /**
     * Builds the transport.
     *
     * @param Clock        $clock         Monotonic clock of the session deadline.
     * @param Closure|null $streamFactory Builds the stream of one session (`Closure(): SmtpStream`);
     *                                    defaults to a new `SocketStream`.
     */
    public function __construct(private readonly Clock $clock, ?Closure $streamFactory = null)
    {
        $this->streamFactory = $streamFactory ?? static fn (): SmtpStream => new SocketStream();
        $this->parser = new ReplyParser();
    }

    /**
     * Runs one SMTP session and returns the raw reply to the final `.`.
     *
     * @param Target           $target      Where to connect, and how to protect the connection.
     * @param SessionSettings  $settings    Timeouts, CA bundle and `EHLO` name.
     * @param Credentials|null $credentials The `AUTH` credentials, or null to send without `AUTH`.
     * @param Envelope         $envelope    The `MAIL FROM` sender and the `RCPT TO` recipients.
     * @param string           $message     The MIME message, not yet dot-stuffed.
     *
     * @return SmtpResult
     *
     * @throws SmtpFailure When the session fails, classified by code and step.
     */
    public function send(
        Target $target,
        SessionSettings $settings,
        #[SensitiveParameter] ?Credentials $credentials,
        Envelope $envelope,
        #[SensitiveParameter] string $message,
    ): SmtpResult {
        $stream = ($this->streamFactory)();
        $session = new SmtpSessionState(
            $stream,
            Deadline::start($this->clock, $settings->totalTimeout),
            $settings,
            $target,
        );

        try {
            $this->connect($session);
            $extensions = $this->ehlo($session);

            if ($target->tlsMode === TlsMode::StartTls) {
                $extensions = $this->startTls($session, $extensions);
            }

            if ($credentials !== null) {
                $this->authenticate($session, $extensions, $credentials);
            }

            $this->envelope($session, $envelope);
            $reply = $this->data($session, $message);
            $this->quit($session);

            return new SmtpResult($reply);
        } finally {
            $stream->close();
        }
    }

    /**
     * Opens the connection and reads the greeting, both within the connect timeout.
     *
     * @param SmtpSessionState $session The session.
     *
     * @return void
     *
     * @throws SmtpFailure When the connection fails or the greeting is not `220`.
     */
    private function connect(SmtpSessionState $session): void
    {
        $target = $session->target;
        $connect = $session->deadline->within($session->settings->connectTimeout);

        try {
            $session->stream->open(
                $target->address,
                $target->port,
                $target->host,
                $target->tlsMode === TlsMode::Implicit,
                $session->settings->caFile,
                $connect,
            );
        } catch (StreamFailure $failure) {
            throw $this->streamFailure($failure, SmtpStep::Connect, $target->tlsMode === TlsMode::Implicit);
        }

        $this->expect($this->read($session, SmtpStep::Greeting, $connect), [self::GREETING_OK], SmtpStep::Greeting);
    }

    /**
     * Sends `EHLO` and reads the extensions; there is no `HELO` fallback.
     *
     * @param SmtpSessionState $session The session.
     *
     * @return EhloExtensions
     *
     * @throws SmtpFailure When `EHLO` is not answered `250`.
     */
    private function ehlo(SmtpSessionState $session): EhloExtensions
    {
        $reply = $this->command($session, 'EHLO ' . $this->ehloName($session), SmtpStep::Ehlo);

        return EhloExtensions::fromReply($this->expect($reply, [self::EHLO_OK], SmtpStep::Ehlo));
    }

    /**
     * Upgrades the connection with `STARTTLS`, then sends `EHLO` again; never downgrades.
     *
     * @param SmtpSessionState $session    The session.
     * @param EhloExtensions   $extensions The pre-TLS extensions.
     *
     * @return EhloExtensions The post-TLS extensions (the pre-TLS list is discarded).
     *
     * @throws SmtpFailure `SMTP_TLS_FAILED` when `STARTTLS` is missing, refused or fails.
     */
    private function startTls(SmtpSessionState $session, EhloExtensions $extensions): EhloExtensions
    {
        if (!$extensions->has('STARTTLS')) {
            $this->quit($session);

            throw new SmtpFailure(FailureCode::TlsFailed, SmtpStep::StartTls);
        }

        $reply = $this->command($session, 'STARTTLS', SmtpStep::StartTls);

        if ($reply->code() !== self::STARTTLS_OK) {
            throw new SmtpFailure(FailureCode::TlsFailed, SmtpStep::StartTls, $reply);
        }

        try {
            $session->stream->upgradeToTls(
                $session->target->host,
                $session->settings->caFile,
                $session->commandBudget(),
            );
        } catch (StreamFailure $failure) {
            throw $this->streamFailure($failure, SmtpStep::StartTls);
        }

        return $this->ehlo($session);
    }

    /**
     * Authenticates with `PLAIN`, else `LOGIN`, from the mechanisms of the last `EHLO`.
     *
     * @param SmtpSessionState $session     The session.
     * @param EhloExtensions   $extensions  The extensions of the last `EHLO`.
     * @param Credentials      $credentials The credentials.
     *
     * @return void
     *
     * @throws SmtpFailure `SMTP_AUTH_FAILED` when no mechanism fits or the server refuses.
     */
    private function authenticate(
        SmtpSessionState $session,
        EhloExtensions $extensions,
        #[SensitiveParameter] Credentials $credentials,
    ): void {
        $mechanisms = $extensions->has('AUTH') ? $extensions->authMechanisms() : [];

        if (in_array(self::PLAIN, $mechanisms, true)) {
            $this->authPlain($session, $credentials);

            return;
        }

        if (in_array(self::LOGIN, $mechanisms, true)) {
            $this->authLogin($session, $credentials);

            return;
        }

        $this->quit($session);

        throw new SmtpFailure(FailureCode::AuthFailed, SmtpStep::Auth);
    }

    /**
     * Authenticates with `AUTH PLAIN` and an initial response.
     *
     * @param SmtpSessionState $session     The session.
     * @param Credentials      $credentials The credentials.
     *
     * @return void
     *
     * @throws SmtpFailure `SMTP_AUTH_FAILED` when the reply is not `235`.
     */
    private function authPlain(SmtpSessionState $session, #[SensitiveParameter] Credentials $credentials): void
    {
        $reply = $this->command(
            $session,
            'AUTH PLAIN ' . base64_encode("\0" . $credentials->username() . "\0" . $credentials->password()->reveal()),
            SmtpStep::Auth,
        );

        $this->expectAuth($reply, self::AUTH_OK);
    }

    /**
     * Authenticates with `AUTH LOGIN`, answering both `334` prompts without parsing them.
     *
     * @param SmtpSessionState $session     The session.
     * @param Credentials      $credentials The credentials.
     *
     * @return void
     *
     * @throws SmtpFailure `SMTP_AUTH_FAILED` when a reply is not the expected one.
     */
    private function authLogin(SmtpSessionState $session, #[SensitiveParameter] Credentials $credentials): void
    {
        $this->expectAuth($this->command($session, 'AUTH LOGIN', SmtpStep::Auth), self::AUTH_CONTINUE);
        $this->expectAuth(
            $this->command($session, base64_encode($credentials->username()), SmtpStep::Auth),
            self::AUTH_CONTINUE,
        );
        $this->expectAuth(
            $this->command($session, base64_encode($credentials->password()->reveal()), SmtpStep::Auth),
            self::AUTH_OK,
        );
    }

    /**
     * Sends `MAIL FROM` (no parameter) and one `RCPT TO` per recipient.
     *
     * @param SmtpSessionState $session  The session.
     * @param Envelope         $envelope The envelope.
     *
     * @return void
     *
     * @throws SmtpFailure When the sender or a recipient is refused (`530` at `MAIL FROM` is
     *                     `SMTP_AUTH_FAILED`).
     */
    private function envelope(SmtpSessionState $session, Envelope $envelope): void
    {
        $reply = $this->command($session, "MAIL FROM:<{$envelope->from}>", SmtpStep::MailFrom);

        if ($reply->code() === self::AUTH_REQUIRED) {
            throw new SmtpFailure(FailureCode::AuthFailed, SmtpStep::MailFrom, $reply);
        }

        $this->expect($reply, [self::MAIL_OK], SmtpStep::MailFrom);

        foreach ($envelope->recipients as $recipient) {
            $reply = $this->command($session, "RCPT TO:<{$recipient}>", SmtpStep::RcptTo);
            $this->expect($reply, self::RCPT_OK, SmtpStep::RcptTo);
        }
    }

    /**
     * Sends `DATA`, the normalized and dot-stuffed message and the final `.`.
     *
     * @param SmtpSessionState $session The session.
     * @param string           $message The MIME message.
     *
     * @return Reply The 2xx reply to the final `.`.
     *
     * @throws SmtpFailure When `DATA` or the message is refused.
     */
    private function data(SmtpSessionState $session, #[SensitiveParameter] string $message): Reply
    {
        $this->expect($this->command($session, 'DATA', SmtpStep::Data), [self::DATA_OK], SmtpStep::Data);

        foreach (self::lines($message) as $line) {
            $this->write($session, str_starts_with($line, '.') ? '.' . $line : $line, SmtpStep::EndOfData);
        }

        $reply = $this->command($session, '.', SmtpStep::EndOfData);

        if (intdiv($reply->code(), 100) === self::SUCCESS_CLASS) {
            return $reply;
        }

        throw $this->replyFailure($reply, SmtpStep::EndOfData);
    }

    /**
     * Sends `QUIT` and reads its reply, best-effort: every failure is ignored.
     *
     * @param SmtpSessionState $session The session.
     *
     * @return void
     */
    private function quit(SmtpSessionState $session): void
    {
        try {
            $session->stream->writeLine('QUIT', $session->commandBudget());
            $this->parser->read($session->stream, $session->commandBudget());
        } catch (StreamFailure | MalformedReply) {
            return;
        }
    }

    /**
     * Writes one command, then reads its reply.
     *
     * @param SmtpSessionState $session The session.
     * @param string           $line    The command, without CRLF.
     * @param SmtpStep         $step    The step, for classification.
     *
     * @return Reply
     *
     * @throws SmtpFailure When the write or the read fails.
     */
    private function command(SmtpSessionState $session, #[SensitiveParameter] string $line, SmtpStep $step): Reply
    {
        $this->write($session, $line, $step);

        return $this->read($session, $step, $session->commandBudget());
    }

    /**
     * Writes one line within the command budget.
     *
     * @param SmtpSessionState $session The session.
     * @param string           $line    The line, without CRLF.
     * @param SmtpStep         $step    The step, for classification.
     *
     * @return void
     *
     * @throws SmtpFailure When the write fails.
     */
    private function write(SmtpSessionState $session, #[SensitiveParameter] string $line, SmtpStep $step): void
    {
        try {
            $session->stream->writeLine($line, $session->commandBudget());
        } catch (StreamFailure $failure) {
            throw $this->streamFailure($failure, $step);
        }
    }

    /**
     * Reads one reply within the budget.
     *
     * @param SmtpSessionState $session The session.
     * @param SmtpStep         $step    The step, for classification.
     * @param Deadline         $budget  Bound of the whole reply.
     *
     * @return Reply
     *
     * @throws SmtpFailure When the read fails or the reply is malformed.
     */
    private function read(SmtpSessionState $session, SmtpStep $step, Deadline $budget): Reply
    {
        try {
            return $this->parser->read($session->stream, $budget);
        } catch (StreamFailure $failure) {
            throw $this->streamFailure($failure, $step);
        } catch (MalformedReply $malformed) {
            throw new SmtpFailure(FailureCode::ProtocolError, $step, null, null, $malformed);
        }
    }

    /**
     * Returns the reply when its code is accepted, else throws its classified failure.
     *
     * @param Reply          $reply    The reply.
     * @param array<int,int> $accepted The accepted codes.
     * @param SmtpStep       $step     The step, for classification.
     *
     * @return Reply
     *
     * @throws SmtpFailure When the code is not accepted.
     */
    private function expect(Reply $reply, array $accepted, SmtpStep $step): Reply
    {
        if (in_array($reply->code(), $accepted, true)) {
            return $reply;
        }

        throw $this->replyFailure($reply, $step);
    }

    /**
     * Fails with `SMTP_AUTH_FAILED` unless the reply has the expected code (the step wins over
     * the reply class).
     *
     * @param Reply   $reply    The reply.
     * @param integer $expected The expected code.
     *
     * @return void
     *
     * @throws SmtpFailure When the code differs.
     */
    private function expectAuth(Reply $reply, int $expected): void
    {
        if ($reply->code() !== $expected) {
            throw new SmtpFailure(FailureCode::AuthFailed, SmtpStep::Auth, $reply);
        }
    }

    /**
     * Classifies an unexpected reply: 5xx `SMTP_REJECTED`, 4xx `SMTP_TEMPORARY_FAILURE`,
     * anything else `SMTP_PROTOCOL_ERROR`.
     *
     * @param Reply    $reply The reply.
     * @param SmtpStep $step  The step.
     *
     * @return SmtpFailure
     */
    private function replyFailure(Reply $reply, SmtpStep $step): SmtpFailure
    {
        $code = match (true) {
            $reply->code() >= self::PERMANENT => FailureCode::Rejected,
            $reply->code() >= self::TRANSIENT => FailureCode::TemporaryFailure,
            default => FailureCode::ProtocolError,
        };

        return new SmtpFailure($code, $step, $reply);
    }

    /**
     * Classifies a stream failure by its kind and step.
     *
     * A `Timeout` from `open()` is before TCP for a plain connect (`SMTP_CONNECTION_FAILED`)
     * and in the handshake for implicit TLS (`SMTP_TIMEOUT`); the socket stream itself reports
     * a TCP connect timeout as `Connect`.
     *
     * @param StreamFailure $failure     The failure.
     * @param SmtpStep      $step        The step.
     * @param boolean       $implicitTls Whether `open()` ran a TLS handshake.
     *
     * @return SmtpFailure
     */
    private function streamFailure(StreamFailure $failure, SmtpStep $step, bool $implicitTls = false): SmtpFailure
    {
        $beforeGreeting = $step === SmtpStep::Connect || $step === SmtpStep::Greeting;

        $code = match ($failure->kind()) {
            StreamFailureKind::Connect => FailureCode::ConnectionFailed,
            StreamFailureKind::Tls, StreamFailureKind::UnexpectedData => FailureCode::TlsFailed,
            StreamFailureKind::Timeout => $step === SmtpStep::Connect && !$implicitTls
                ? FailureCode::ConnectionFailed
                : FailureCode::Timeout,
            StreamFailureKind::Closed => $beforeGreeting ? FailureCode::ConnectionFailed : FailureCode::ProtocolError,
        };

        return new SmtpFailure($code, $step, null, $failure->reason(), $failure);
    }

    /**
     * Returns the `EHLO` argument: the configured name, else the local address literal.
     *
     * @param SmtpSessionState $session The session.
     *
     * @return string
     */
    private function ehloName(SmtpSessionState $session): string
    {
        $name = $session->settings->ehloName;

        if ($name !== null && $name !== '') {
            return $name;
        }

        $address = $session->stream->localAddress();

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return "[IPv6:{$address}]";
        }

        return "[{$address}]";
    }

    /**
     * Splits the message on CRLF, bare CR and bare LF; a final line break ends the last line
     * rather than starting an empty one.
     *
     * @param string $message The MIME message.
     *
     * @return list<string>
     */
    private static function lines(#[SensitiveParameter] string $message): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $message) ?: [];

        if (count($lines) > 1 && end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }
}
