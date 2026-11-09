<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

use SensitiveParameter;

/**
 * A line-oriented connection to an SMTP server, with TLS.
 *
 * Every wait is bounded by the deadline it receives; a deadline already expired fails at once
 * with a `Timeout`, without any I/O. Failures are `StreamFailure`s.
 */
interface SmtpStream
{
    /**
     * Opens the TCP connection, then the TLS handshake when `$implicitTls` is set.
     *
     * @param string      $address     IP address to connect to (already checked).
     * @param integer     $port        TCP port.
     * @param string      $peerName    Name the certificate must match (the connection's host).
     * @param boolean     $implicitTls Whether to negotiate TLS right after connecting.
     * @param string|null $caFile      Absolute path of the CA bundle, or null for the system one.
     * @param Deadline    $deadline    Bound of the whole operation.
     *
     * @return void
     *
     * @throws StreamFailure `Connect`, `Tls` or `Timeout`.
     */
    public function open(
        string $address,
        int $port,
        string $peerName,
        bool $implicitTls,
        ?string $caFile,
        Deadline $deadline,
    ): void;

    /**
     * Returns the raw IP address of the local end of the connection.
     *
     * @return string
     */
    public function localAddress(): string;

    /**
     * Writes one line, adding the CRLF; loops until every byte is written.
     *
     * @param string   $line     The line, without CRLF.
     * @param Deadline $deadline Bound of the write.
     *
     * @return void
     *
     * @throws StreamFailure `Timeout` or `Closed`.
     */
    public function writeLine(#[SensitiveParameter] string $line, Deadline $deadline): void;

    /**
     * Reads raw bytes up to and including LF, at most `ReplyParser::MAX_LINE_BYTES + 2` bytes.
     *
     * A result without a trailing LF means the line is too long or the peer closed mid-line.
     *
     * @param Deadline $deadline Bound of the read.
     *
     * @return string
     *
     * @throws StreamFailure `Timeout`, or `Closed` when the peer closes before any byte.
     */
    public function readLine(Deadline $deadline): string;

    /**
     * Negotiates TLS on the open connection (`STARTTLS`).
     *
     * @param string      $peerName Name the certificate must match.
     * @param string|null $caFile   Absolute path of the CA bundle, or null for the system one.
     * @param Deadline    $deadline Bound of the handshake.
     *
     * @return void
     *
     * @throws StreamFailure `UnexpectedData` when bytes are pending before the handshake,
     *                       `Tls` or `Timeout`.
     */
    public function upgradeToTls(string $peerName, ?string $caFile, Deadline $deadline): void;

    /**
     * Closes the connection; idempotent.
     *
     * @return void
     */
    public function close(): void;
}
