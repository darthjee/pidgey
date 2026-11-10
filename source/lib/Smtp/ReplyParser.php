<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

/**
 * Reads one SMTP reply from a stream (RFC 5321 §4.2), within the parser's limits.
 *
 * Lines are `NNN-text` (continuation) and `NNN text` or `NNN` (last line), all with the same
 * code, from 200 to 599. CRLF is expected and a bare LF tolerated. The bytes of the text are
 * kept untouched.
 */
final class ReplyParser
{
    /** Most lines in one reply. */
    public const MAX_LINES = 100;

    /** Most bytes in one line, without the CRLF. */
    public const MAX_LINE_BYTES = 2048;

    private const LINE_PATTERN = '/^([0-9]{3})(?:([- ])(.*))?$/sD';

    private const MIN_CODE = 200;
    private const MAX_CODE = 599;

    /**
     * Reads the next reply; the one budget bounds the whole reply, not each line.
     *
     * @param SmtpStream $stream The stream to read from.
     * @param Deadline   $budget Bound of the whole reply.
     *
     * @return Reply
     *
     * @throws MalformedReply When the reply is malformed, exceeds a limit or the peer closes
     *                        mid-reply.
     * @throws StreamFailure  When the first line fails (`Closed`, `Timeout`), or a later line
     *                        times out.
     */
    public function read(SmtpStream $stream, Deadline $budget): Reply
    {
        $code = null;
        $texts = [];

        while (true) {
            [$lineCode, $last, $text] = $this->parseLine($this->readLine($stream, $budget, $texts === []));

            if ($code !== null && $lineCode !== $code) {
                throw new MalformedReply();
            }

            $code = $lineCode;
            $texts[] = $text;

            if ($last) {
                return new Reply($code, $texts);
            }

            if (count($texts) >= self::MAX_LINES) {
                throw new MalformedReply();
            }
        }
    }

    /**
     * Reads one raw line; a close after the first line is a malformed reply.
     *
     * @param SmtpStream $stream The stream to read from.
     * @param Deadline   $budget Bound of the whole reply.
     * @param boolean    $first  Whether this is the reply's first line.
     *
     * @return string
     *
     * @throws MalformedReply When the peer closes mid-reply.
     * @throws StreamFailure  Otherwise.
     */
    private function readLine(SmtpStream $stream, Deadline $budget, bool $first): string
    {
        try {
            return $stream->readLine($budget);
        } catch (StreamFailure $failure) {
            if ($first || $failure->kind() !== StreamFailureKind::Closed) {
                throw $failure;
            }

            throw new MalformedReply();
        }
    }

    /**
     * Splits one raw line into its code, whether it is the last line, and its text.
     *
     * @param string $raw Raw bytes, with the line ending.
     *
     * @return array{integer, boolean, string}
     *
     * @throws MalformedReply When the line is malformed or too long.
     */
    private function parseLine(string $raw): array
    {
        if (!str_ends_with($raw, "\n")) {
            throw new MalformedReply();
        }

        $content = substr($raw, 0, -1);

        if (str_ends_with($content, "\r")) {
            $content = substr($content, 0, -1);
        }

        if (strlen($content) > self::MAX_LINE_BYTES || preg_match(self::LINE_PATTERN, $content, $match) !== 1) {
            throw new MalformedReply();
        }

        $code = (int) $match[1];

        if ($code < self::MIN_CODE || $code > self::MAX_CODE) {
            throw new MalformedReply();
        }

        return [$code, ($match[2] ?? ' ') === ' ', $match[3] ?? ''];
    }
}
