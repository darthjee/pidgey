<?php

declare(strict_types=1);

namespace Pidgey\Email;

use Pidgey\Infra\SecretString;
use SensitiveParameter;

/**
 * Cleans an SMTP reply text before it is used in a response or a log line
 * (`email/failures.md#echo-redaction`, `#sanitization-and-length-cap`).
 */
final class ReplyText
{
    private const REDACTED = '[redacted]';
    private const MAX_LENGTH = 512;
    private const ELLIPSIS = '...';

    /**
     * Redacts the echoed secrets, then sanitizes and caps the text.
     *
     * - The base64 `AUTH PLAIN` payload, the base64 password and the password become
     *   `[redacted]`.
     * - Bytes outside printable ASCII become `?`, tabs become spaces, runs of spaces collapse
     *   to one and the text is trimmed.
     * - A text over 512 characters is cut to 509 plus `...`.
     *
     * @param string            $text     The joined reply text, raw bytes.
     * @param SecretString|null $password The password of the session, or null; not wiped yet.
     * @param string|null       $username The username of the session, or null.
     *
     * @return string
     */
    public static function clean(
        string $text,
        #[SensitiveParameter] ?SecretString $password,
        ?string $username,
    ): string {
        return self::sanitize(self::redact($text, $password, $username));
    }

    /**
     * Replaces every echo of the secret forms with `[redacted]`, longest first.
     *
     * @param string            $text     The reply text.
     * @param SecretString|null $password The password, or null.
     * @param string|null       $username The username, or null.
     *
     * @return string
     */
    private static function redact(
        string $text,
        #[SensitiveParameter] ?SecretString $password,
        ?string $username,
    ): string {
        if ($password === null || $password->reveal() === '') {
            return $text;
        }

        $secrets = [base64_encode($password->reveal()), $password->reveal()];

        if ($username !== null) {
            array_unshift($secrets, base64_encode("\0" . $username . "\0" . $password->reveal()));
        }

        return str_replace($secrets, self::REDACTED, $text);
    }

    /**
     * Keeps printable ASCII only, collapses whitespace and caps the length.
     *
     * @param string $text The redacted text.
     *
     * @return string
     */
    private static function sanitize(string $text): string
    {
        $text = (string) preg_replace('/[^\x20-\x7E]/', '?', str_replace("\t", ' ', $text));
        $text = trim((string) preg_replace('/ {2,}/', ' ', $text));

        if (strlen($text) <= self::MAX_LENGTH) {
            return $text;
        }

        return substr($text, 0, self::MAX_LENGTH - strlen(self::ELLIPSIS)) . self::ELLIPSIS;
    }
}
