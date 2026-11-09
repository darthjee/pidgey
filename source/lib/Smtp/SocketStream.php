<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

use LogicException;
use SensitiveParameter;

/**
 * `SmtpStream` over a non-blocking PHP socket, with TLS through the `TlsContext` policy.
 *
 * The connection is always opened as `tcp://`; implicit TLS and `STARTTLS` both run the
 * handshake in place with `stream_socket_enable_crypto()`. Every wait goes through
 * `stream_select()`, bounded by the deadline of the operation.
 */
final class SocketStream implements SmtpStream
{
    private const CRYPTO_METHOD = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
    private const READ_CHUNK_BYTES = 8192;
    private const MICROSECONDS = 1000000;

    /** @var resource|null */
    private $socket = null;

    /** Bytes read from the socket and not yet returned by `readLine()`. */
    private string $buffer = '';

    /**
     * Opens the TCP connection, then the TLS handshake when `$implicitTls` is set.
     *
     * @param string      $address     IP address to connect to (IPv6 is bracketed here).
     * @param integer     $port        TCP port.
     * @param string      $peerName    Name the certificate must match.
     * @param boolean     $implicitTls Whether to negotiate TLS right after connecting.
     * @param string|null $caFile      Absolute path of the CA bundle, or null for the system one.
     * @param Deadline    $deadline    Bound of the connect and the handshake.
     *
     * @return void
     *
     * @throws StreamFailure `Connect` (refused, unreachable, timeout before TCP), `Tls` or
     *                       `Timeout`.
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable) The connect error code and message are
     *                                              by-reference outputs, deliberately unused.
     */
    public function open(
        string $address,
        int $port,
        string $peerName,
        bool $implicitTls,
        ?string $caFile,
        Deadline $deadline,
    ): void {
        $this->close();

        if ($deadline->expired()) {
            throw new StreamFailure(StreamFailureKind::Connect);
        }

        $host = str_contains($address, ':') ? "[{$address}]" : $address;
        $socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errorCode,
            $errorMessage,
            $deadline->remaining(),
            STREAM_CLIENT_CONNECT,
            stream_context_create(TlsContext::options($peerName, $caFile)),
        );

        if ($socket === false) {
            throw new StreamFailure(StreamFailureKind::Connect);
        }

        stream_set_blocking($socket, false);
        $this->socket = $socket;

        if ($implicitTls) {
            $this->handshake($peerName, $caFile, $deadline);
        }
    }

    /**
     * Returns the raw IP address of the local end of the connection.
     *
     * @return string
     *
     * @throws LogicException When the stream is not open.
     */
    public function localAddress(): string
    {
        $name = (string) stream_socket_get_name($this->socket(), false);
        $separator = strrpos($name, ':');
        $address = $separator === false ? $name : substr($name, 0, $separator);

        return trim($address, '[]');
    }

    /**
     * Writes one line and its CRLF, looping until every byte is written.
     *
     * @param string   $line     The line, without CRLF.
     * @param Deadline $deadline Bound of the write.
     *
     * @return void
     *
     * @throws StreamFailure `Timeout` or `Closed`.
     */
    public function writeLine(#[SensitiveParameter] string $line, Deadline $deadline): void
    {
        $socket = $this->socket();
        $data = $line . "\r\n";
        $waited = false;

        while ($data !== '') {
            $this->checkTime($deadline);
            $written = @fwrite($socket, $data);

            if ($written === false || ($written === 0 && $waited)) {
                throw new StreamFailure(StreamFailureKind::Closed);
            }

            $data = substr($data, $written);
            $waited = $written === 0 && $this->wait($deadline, true);
        }
    }

    /**
     * Reads raw bytes up to and including LF, at most `ReplyParser::MAX_LINE_BYTES + 2` bytes;
     * a partial line is returned as is when the peer closes.
     *
     * @param Deadline $deadline Bound of the read.
     *
     * @return string
     *
     * @throws StreamFailure `Timeout`, or `Closed` when the peer closes before any byte.
     */
    public function readLine(Deadline $deadline): string
    {
        $this->socket();
        $this->checkTime($deadline);
        $limit = ReplyParser::MAX_LINE_BYTES + 2;

        while (true) {
            $end = strpos($this->buffer, "\n");

            if ($end !== false && $end < $limit) {
                return $this->take($end + 1);
            }

            if (strlen($this->buffer) >= $limit) {
                return $this->take($limit);
            }

            $chunk = $this->readChunk($deadline);

            if ($chunk === null) {
                return $this->takeRestAfterClose();
            }

            $this->buffer .= $chunk;
        }
    }

    /**
     * Negotiates TLS on the open connection, refusing any byte received before the handshake.
     *
     * @param string      $peerName Name the certificate must match.
     * @param string|null $caFile   Absolute path of the CA bundle, or null for the system one.
     * @param Deadline    $deadline Bound of the handshake.
     *
     * @return void
     *
     * @throws StreamFailure `UnexpectedData`, `Tls` or `Timeout`.
     */
    public function upgradeToTls(string $peerName, ?string $caFile, Deadline $deadline): void
    {
        $socket = $this->socket();
        $this->checkTime($deadline);

        if ($this->buffer !== '' || stream_get_meta_data($socket)['unread_bytes'] > 0) {
            throw new StreamFailure(StreamFailureKind::UnexpectedData);
        }

        $pending = @fread($socket, 1);

        if ($pending !== false && $pending !== '') {
            throw new StreamFailure(StreamFailureKind::UnexpectedData);
        }

        $this->handshake($peerName, $caFile, $deadline);
    }

    /**
     * Closes the connection; idempotent.
     *
     * @return void
     */
    public function close(): void
    {
        if ($this->socket !== null) {
            @fclose($this->socket);
        }

        $this->socket = null;
        $this->buffer = '';
    }

    /**
     * Runs the client TLS handshake on the open socket, within the deadline.
     *
     * @param string      $peerName Name the certificate must match.
     * @param string|null $caFile   Absolute path of the CA bundle, or null.
     * @param Deadline    $deadline Bound of the handshake.
     *
     * @return void
     *
     * @throws StreamFailure `Tls` with the OpenSSL reason, or `Timeout`.
     */
    private function handshake(string $peerName, ?string $caFile, Deadline $deadline): void
    {
        $socket = $this->socket();
        stream_context_set_options($socket, TlsContext::options($peerName, $caFile));
        self::clearOpensslErrors();

        while (true) {
            $this->checkTime($deadline);
            error_clear_last();
            $result = @stream_socket_enable_crypto($socket, true, self::CRYPTO_METHOD);

            if ($result === true) {
                return;
            }

            if ($result === false) {
                throw new StreamFailure(StreamFailureKind::Tls, self::tlsReason());
            }

            $this->wait($deadline, false);
        }
    }

    /**
     * Reads what the socket has, waiting for it within the deadline.
     *
     * @param Deadline $deadline Bound of the read.
     *
     * @return string|null The bytes read, or null when the peer closed the connection.
     *
     * @throws StreamFailure `Timeout`.
     */
    private function readChunk(Deadline $deadline): ?string
    {
        $socket = $this->socket();

        while (true) {
            $chunk = @fread($socket, self::READ_CHUNK_BYTES);

            if ($chunk === false) {
                return null;
            }

            if ($chunk !== '') {
                return $chunk;
            }

            if (feof($socket)) {
                return null;
            }

            $this->wait($deadline, false);
        }
    }

    /**
     * Returns the partial line left when the peer closed, or fails when nothing is left.
     *
     * @return string
     *
     * @throws StreamFailure `Closed` when the peer closed before any byte of the line.
     */
    private function takeRestAfterClose(): string
    {
        if ($this->buffer === '') {
            throw new StreamFailure(StreamFailureKind::Closed);
        }

        return $this->take(strlen($this->buffer));
    }

    /**
     * Removes and returns the first bytes of the buffer.
     *
     * @param integer $length Number of bytes.
     *
     * @return string
     */
    private function take(int $length): string
    {
        $line = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, $length);

        return $line;
    }

    /**
     * Waits until the socket is readable (or writable), within the deadline.
     *
     * @param Deadline $deadline Bound of the wait.
     * @param boolean  $forWrite Whether to wait for writability instead of readability.
     *
     * @return boolean Whether the socket is ready (false when the wait was interrupted).
     *
     * @throws StreamFailure `Timeout` when the deadline is reached.
     */
    private function wait(Deadline $deadline, bool $forWrite): bool
    {
        $this->checkTime($deadline);
        $remaining = $deadline->remaining();
        $seconds = (int) floor($remaining);
        $microseconds = (int) (($remaining - $seconds) * self::MICROSECONDS);
        $read = $forWrite ? [] : [$this->socket()];
        $write = $forWrite ? [$this->socket()] : [];
        $except = [];

        $ready = @stream_select($read, $write, $except, $seconds, $microseconds);

        if ($ready === 0) {
            $this->checkTime($deadline);
        }

        return $ready !== false && $ready > 0;
    }

    /**
     * Fails at once, without any I/O, when the deadline has expired.
     *
     * @param Deadline $deadline The deadline.
     *
     * @return void
     *
     * @throws StreamFailure `Timeout`.
     */
    private function checkTime(Deadline $deadline): void
    {
        if ($deadline->expired()) {
            throw new StreamFailure(StreamFailureKind::Timeout);
        }
    }

    /**
     * Returns the open socket.
     *
     * @return resource
     *
     * @throws LogicException When the stream is not open.
     */
    private function socket()
    {
        if ($this->socket === null) {
            throw new LogicException('SocketStream: not open');
        }

        return $this->socket;
    }

    /**
     * Empties the OpenSSL error queue, so that a failure reports its own reasons only.
     *
     * @return void
     */
    private static function clearOpensslErrors(): void
    {
        while (openssl_error_string() !== false) {
            continue;
        }
    }

    /**
     * Returns the reason of the last TLS failure: the OpenSSL error queue, else the PHP warning.
     *
     * @return string|null
     */
    private static function tlsReason(): ?string
    {
        $reasons = [];

        while (($reason = openssl_error_string()) !== false) {
            $reasons[] = $reason;
        }

        if ($reasons !== []) {
            return implode('; ', $reasons);
        }

        $message = error_get_last()['message'] ?? null;

        if ($message === null) {
            return null;
        }

        return (string) preg_replace('/^stream_socket_enable_crypto\(\): /', '', $message);
    }
}
