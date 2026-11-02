<?php

declare(strict_types=1);

namespace Pidgey\Http;

use Pidgey\Infra\Env;

/**
 * The list of trusted origins, matched exactly against a request Origin.
 *
 * Built from ALLOWED_ORIGINS, falling back to the origin of FRONTEND_BASE_URL. Entries are
 * not validated here: ConfigValidator refuses boot on invalid ones.
 */
final readonly class TrustedOrigins
{
    private const NULL_ORIGIN = 'null';

    /** @var list<string> */
    private array $origins;

    /**
     * Builds the list.
     *
     * @param string[] $origins Trusted origins, as bare scheme://host[:port] strings.
     */
    public function __construct(array $origins)
    {
        $this->origins = array_values($origins);
    }

    /**
     * Builds the list from ALLOWED_ORIGINS, or from FRONTEND_BASE_URL when it has no entries.
     *
     * @return self
     */
    public static function fromEnv(): self
    {
        $origins = self::splitList((string) Env::get('ALLOWED_ORIGINS', ''));

        if ($origins === []) {
            $origins = self::originOf((string) Env::get('FRONTEND_BASE_URL', ''));
        }

        return new self($origins);
    }

    /**
     * Tells whether the origin is trusted, by exact string comparison. "null" is never trusted.
     *
     * @param string $origin Request Origin header value.
     *
     * @return boolean
     */
    public function contains(string $origin): bool
    {
        return $origin !== self::NULL_ORIGIN && in_array($origin, $this->origins, true);
    }

    /**
     * Returns the trusted origins.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return $this->origins;
    }

    /**
     * Reduces a URL to its scheme://host[:port] origin.
     *
     * @param string $url URL to reduce.
     *
     * @return list<string> The origin, or an empty list when the URL is empty or unparseable.
     */
    private static function originOf(string $url): array
    {
        $parts = $url === '' ? false : parse_url($url);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || $parts['host'] === '') {
            return [];
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];

        return [isset($parts['port']) ? $origin . ':' . $parts['port'] : $origin];
    }

    /**
     * Splits a comma-separated list, trimming entries and dropping empty ones.
     *
     * @param string $value Comma-separated list.
     *
     * @return list<string>
     */
    private static function splitList(string $value): array
    {
        $entries = array_map('trim', explode(',', $value));

        return array_values(array_filter($entries, static fn (string $entry) => $entry !== ''));
    }
}
