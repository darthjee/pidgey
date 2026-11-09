<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

/**
 * The extensions advertised in an `EHLO` reply.
 *
 * The first line is the server's greeting name, never an extension. Keywords are
 * case-insensitive; `AUTH` mechanisms are read from both `AUTH PLAIN LOGIN` and the legacy
 * `AUTH=LOGIN PLAIN` form, and merged across lines.
 */
final class EhloExtensions
{
    private const AUTH = 'AUTH';

    /**
     * Builds the list.
     *
     * @param array<string, true> $keywords   Upper-case keywords.
     * @param array<int, string>  $mechanisms Upper-case, de-duplicated `AUTH` mechanisms.
     */
    private function __construct(private readonly array $keywords, private readonly array $mechanisms)
    {
    }

    /**
     * Reads the extensions of an `EHLO` reply.
     *
     * @param Reply $reply The `EHLO` reply.
     *
     * @return self
     */
    public static function fromReply(Reply $reply): self
    {
        $keywords = [];
        $mechanisms = [];

        foreach (array_slice($reply->lines(), 1) as $line) {
            $words = preg_split('/ +/', trim($line, ' '), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if ($words === []) {
                continue;
            }

            $keyword = strtoupper(array_shift($words));

            if (str_starts_with($keyword, self::AUTH . '=')) {
                array_unshift($words, substr($keyword, strlen(self::AUTH) + 1));
                $keyword = self::AUTH;
            }

            $keywords[$keyword] = true;

            if ($keyword === self::AUTH) {
                $mechanisms = [...$mechanisms, ...array_map('strtoupper', $words)];
            }
        }

        return new self($keywords, array_values(array_unique(array_filter($mechanisms, 'strlen'))));
    }

    /**
     * Tells whether the server advertises the extension (case-insensitive).
     *
     * @param string $keyword Extension keyword, such as `STARTTLS`.
     *
     * @return boolean
     */
    public function has(string $keyword): bool
    {
        return isset($this->keywords[strtoupper($keyword)]);
    }

    /**
     * Returns the advertised `AUTH` mechanisms, upper case and de-duplicated.
     *
     * @return list<string>
     */
    public function authMechanisms(): array
    {
        return $this->mechanisms;
    }
}
