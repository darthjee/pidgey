<?php

declare(strict_types=1);

namespace Pidgey\Http;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * One `Set-Cookie` header line; PHP's `setcookie()` is never used.
 *
 * Attributes are written only when set, in the order HttpOnly, Secure, SameSite, Path,
 * Max-Age, Expires. No `Domain` attribute is ever written: cookies are host-only.
 */
final readonly class SetCookie
{
    /** Characters refused in names and values: they would split the line or the attributes. */
    private const FORBIDDEN_CHARACTERS = "\r\n;,";

    /**
     * Builds the cookie.
     *
     * @param string       $name     Cookie name.
     * @param string       $value    Cookie value; may be empty.
     * @param string|null  $path     The `Path` attribute.
     * @param integer|null $maxAge   The `Max-Age` attribute, in seconds.
     * @param string|null  $expires  The `Expires` attribute, an HTTP date.
     * @param boolean      $httpOnly Whether to write `HttpOnly`.
     * @param boolean      $secure   Whether to write `Secure`.
     * @param string|null  $sameSite The `SameSite` attribute, e.g. "Strict".
     *
     * @throws InvalidArgumentException When the name or the value contains CR, LF, `;` or `,`.
     */
    public function __construct(
        public string $name,
        #[SensitiveParameter] public string $value,
        public ?string $path = null,
        public ?int $maxAge = null,
        public ?string $expires = null,
        public bool $httpOnly = true,
        public bool $secure = true,
        public ?string $sameSite = null,
    ) {
        if (strpbrk($name, self::FORBIDDEN_CHARACTERS) !== false) {
            throw new InvalidArgumentException('Cookie names must not contain CR, LF, ";" or ",".');
        }

        if (strpbrk($value, self::FORBIDDEN_CHARACTERS) !== false) {
            throw new InvalidArgumentException('Cookie values must not contain CR, LF, ";" or ",".');
        }
    }

    /**
     * Returns the full header line, e.g. "Set-Cookie: a=b; HttpOnly; Secure; Path=/".
     *
     * @return string
     */
    public function line(): string
    {
        $parts = ["{$this->name}={$this->value}"];

        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }

        if ($this->secure) {
            $parts[] = 'Secure';
        }

        $attributes = [
            'SameSite' => $this->sameSite,
            'Path' => $this->path,
            'Max-Age' => $this->maxAge,
            'Expires' => $this->expires,
        ];

        foreach ($attributes as $attribute => $value) {
            if ($value !== null) {
                $parts[] = "{$attribute}={$value}";
            }
        }

        return 'Set-Cookie: ' . implode('; ', $parts);
    }

    /**
     * Adds the header line to a response.
     *
     * @param Response $response The response to add the cookie to.
     *
     * @return void
     */
    public function applyTo(Response $response): void
    {
        $response->addHeader($this->line());
    }
}
