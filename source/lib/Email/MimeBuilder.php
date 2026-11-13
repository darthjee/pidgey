<?php

declare(strict_types=1);

namespace Pidgey\Email;

use Closure;
use DateTimeZone;
use LogicException;
use Pidgey\Infra\WallClock;

/**
 * Builds the MIME message of a send (`email/message-scope.md#message-format`).
 *
 * - Headers, in order: `Date` (UTC), `From`, `To`, `Subject`, `Message-ID`, `MIME-Version`,
 *   `Content-Type` and, for a single part, `Content-Transfer-Encoding`.
 * - The subject is kept as is when it is printable ASCII, else encoded as RFC 2047
 *   `=?UTF-8?B?...?=` words of at most 75 characters that never split a UTF-8 character,
 *   on lines of at most 76 characters; a printable ASCII subject is folded at 78 characters.
 * - Every body is normalized to CRLF, then quoted-printable with lines of at most 76
 *   characters; text and HTML together become `multipart/alternative`, text first.
 * - CRLF throughout; no dot-stuffing and no final `.` (the transport's job).
 *
 * As a backstop to upstream validation, it throws a `LogicException` on a control character
 * in the subject and on a non-ASCII or control byte in an address.
 */
final class MimeBuilder
{
    private const CRLF = "\r\n";
    private const RANDOM_BYTES = 16;
    private const DATE_FORMAT = 'D, d M Y H:i:s +0000';
    private const FOLD_LIMIT = 78;
    private const ENCODED_LINE_LIMIT = 76;
    private const QP_LIMIT = 76;
    private const SUBJECT = 'Subject:';
    private const WORD_PREFIX = '=?UTF-8?B?';
    private const WORD_SUFFIX = '?=';
    private const WORD_LIMIT = 75;
    private const TEXT_TYPE = 'text/plain; charset=UTF-8';
    private const HTML_TYPE = 'text/html; charset=UTF-8';

    /** @var Closure(): string */
    private readonly Closure $random;

    /**
     * Builds the MIME builder.
     *
     * @param WallClock    $clock  Source of the `Date` header.
     * @param Closure|null $random Returns 16 random bytes (`Closure(): string`), for the
     *                             `Message-ID` and the boundary; defaults to `random_bytes`.
     */
    public function __construct(private readonly WallClock $clock, ?Closure $random = null)
    {
        $this->random = $random ?? static fn (): string => random_bytes(self::RANDOM_BYTES);
    }

    /**
     * Builds the full message: headers, a blank line, then the body.
     *
     * @param string  $fromAddress The sender address (`From`, and the `Message-ID` domain).
     * @param Message $message     The message.
     *
     * @return string
     *
     * @throws LogicException When an address or the subject is unsafe.
     */
    public function build(string $fromAddress, Message $message): string
    {
        self::checkAddress($fromAddress);
        self::checkAddress($message->to);
        self::checkSubject($message->subject);

        $headers = [
            'Date: ' . $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format(self::DATE_FORMAT),
            'From: ' . $fromAddress,
            'To: ' . $message->to,
            self::subject($message->subject),
            'Message-ID: <' . $this->hex() . '@' . self::domain($fromAddress) . '>',
            'MIME-Version: 1.0',
        ];

        [$contentHeaders, $body] = $this->body($message);

        return implode(self::CRLF, [...$headers, ...$contentHeaders]) . self::CRLF . self::CRLF . $body;
    }

    /**
     * Returns the content headers and the encoded body.
     *
     * @param Message $message The message.
     *
     * @return array{0: list<string>, 1: string}
     */
    private function body(Message $message): array
    {
        if ($message->html === null) {
            return [self::partHeaders(self::TEXT_TYPE), self::quotedPrintable((string) $message->text)];
        }

        if ($message->text === null) {
            return [self::partHeaders(self::HTML_TYPE), self::quotedPrintable($message->html)];
        }

        $boundary = '=_pidgey_' . $this->hex();
        $body = implode(self::CRLF, [
            '--' . $boundary,
            ...self::partHeaders(self::TEXT_TYPE),
            '',
            self::quotedPrintable($message->text),
            '--' . $boundary,
            ...self::partHeaders(self::HTML_TYPE),
            '',
            self::quotedPrintable($message->html),
            '--' . $boundary . '--',
            '',
        ]);

        return [['Content-Type: multipart/alternative; boundary="' . $boundary . '"'], $body];
    }

    /**
     * Returns the content headers of one quoted-printable part.
     *
     * @param string $contentType The `Content-Type` value.
     *
     * @return list<string>
     */
    private static function partHeaders(string $contentType): array
    {
        return ['Content-Type: ' . $contentType, 'Content-Transfer-Encoding: quoted-printable'];
    }

    /**
     * Returns 32 hex characters from 16 random bytes.
     *
     * @return string
     */
    private function hex(): string
    {
        return bin2hex(($this->random)());
    }

    /**
     * Refuses an address with a non-ASCII byte or a control character (CR and LF included).
     *
     * @param string $address The address.
     *
     * @return void
     *
     * @throws LogicException When the address is unsafe.
     */
    private static function checkAddress(string $address): void
    {
        if (preg_match('/[^\x20-\x7E]/', $address) === 1) {
            throw new LogicException('MimeBuilder: unsafe address');
        }
    }

    /**
     * Refuses a subject with a control character, or that is not UTF-8.
     *
     * @param string $subject The subject.
     *
     * @return void
     *
     * @throws LogicException When the subject is unsafe.
     */
    private static function checkSubject(string $subject): void
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $subject) === 1) {
            throw new LogicException('MimeBuilder: control character in subject');
        }

        if (preg_match('//u', $subject) !== 1) {
            throw new LogicException('MimeBuilder: subject is not UTF-8');
        }
    }

    /**
     * Returns the domain of an address: what follows its last `@`.
     *
     * @param string $address The address.
     *
     * @return string
     *
     * @throws LogicException When the address has no domain.
     */
    private static function domain(string $address): string
    {
        $at = strrpos($address, '@');

        if ($at === false || $at === strlen($address) - 1) {
            throw new LogicException('MimeBuilder: address without domain');
        }

        return substr($address, $at + 1);
    }

    /**
     * Returns the folded `Subject` header.
     *
     * @param string $subject The subject.
     *
     * @return string
     */
    private static function subject(string $subject): string
    {
        if (preg_match('/^[\x20-\x7E]*$/D', $subject) === 1) {
            return self::fold($subject);
        }

        return self::SUBJECT . ' ' . implode(self::CRLF . ' ', self::encodedWords($subject));
    }

    /**
     * Folds a printable ASCII subject at its spaces so that each line has at most 78
     * characters where possible; a word longer than a line is kept whole.
     *
     * @param string $subject The subject.
     *
     * @return string
     */
    private static function fold(string $subject): string
    {
        $lines = [];
        $current = self::SUBJECT;

        foreach (explode(' ', $subject) as $word) {
            $candidate = $current . ' ' . $word;

            if (strlen($candidate) > self::FOLD_LIMIT && $current !== self::SUBJECT && $word !== '') {
                $lines[] = $current;
                $candidate = ' ' . $word;
            }

            $current = $candidate;
        }

        $lines[] = $current;

        return implode(self::CRLF, $lines);
    }

    /**
     * Splits the subject into encoded-words of at most 75 characters, at UTF-8 character
     * boundaries; the first word also fits after `Subject: ` within 76 characters, the limit
     * of a line holding encoded-words (RFC 2047 section 2).
     *
     * @param string $subject The subject, valid UTF-8.
     *
     * @return list<string>
     */
    private static function encodedWords(string $subject): array
    {
        $words = [];
        $chunk = '';
        $limit = self::payloadBytes(self::ENCODED_LINE_LIMIT - strlen(self::SUBJECT . ' '));

        foreach (preg_split('//u', $subject, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if ($chunk !== '' && strlen($chunk . $character) > $limit) {
                $words[] = self::encodedWord($chunk);
                $chunk = '';
                $limit = self::payloadBytes(self::WORD_LIMIT);
            }

            $chunk .= $character;
        }

        $words[] = self::encodedWord($chunk);

        return $words;
    }

    /**
     * Returns the most bytes whose encoded-word fits in the given length.
     *
     * @param integer $length The longest encoded-word allowed.
     *
     * @return integer
     */
    private static function payloadBytes(int $length): int
    {
        $base64 = $length - strlen(self::WORD_PREFIX . self::WORD_SUFFIX);

        return intdiv($base64, 4) * 3;
    }

    /**
     * Returns one `B` encoded-word.
     *
     * @param string $chunk The UTF-8 bytes.
     *
     * @return string
     */
    private static function encodedWord(string $chunk): string
    {
        return self::WORD_PREFIX . base64_encode($chunk) . self::WORD_SUFFIX;
    }

    /**
     * Normalizes line endings to CRLF, then encodes the text as quoted-printable.
     *
     * @param string $text The body.
     *
     * @return string
     */
    private static function quotedPrintable(string $text): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        return implode(self::CRLF, array_map(self::quotedPrintableLine(...), $lines));
    }

    /**
     * Encodes one line: `=`, control and non-ASCII bytes, and whitespace at the end of the
     * line become `=XX`; lines over 76 characters get soft breaks, never inside an `=XX`.
     *
     * @param string $line The line, without its line break.
     *
     * @return string
     */
    private static function quotedPrintableLine(string $line): string
    {
        $tokens = self::quotedPrintableTokens($line);
        $count = count($tokens);
        $segments = [];
        $current = '';

        foreach ($tokens as $index => $token) {
            $length = strlen($current . $token);
            $fits = $index === $count - 1 ? $length <= self::QP_LIMIT : $length < self::QP_LIMIT;

            if (!$fits) {
                $segments[] = $current . '=';
                $current = '';
            }

            $current .= $token;
        }

        $segments[] = $current;

        return implode(self::CRLF, $segments);
    }

    /**
     * Returns the encoded tokens of one line: a literal byte or an `=XX` escape each.
     *
     * @param string $line The line, without its line break.
     *
     * @return list<string>
     */
    private static function quotedPrintableTokens(string $line): array
    {
        $tokens = [];
        $last = strlen($line) - 1;

        foreach (str_split($line) as $index => $byte) {
            $ord = ord($byte);
            $literal = ($ord >= 33 && $ord <= 126 && $byte !== '=')
                || (($byte === ' ' || $byte === "\t") && $index !== $last);

            $tokens[] = $literal ? $byte : sprintf('=%02X', $ord);
        }

        return $tokens;
    }
}
