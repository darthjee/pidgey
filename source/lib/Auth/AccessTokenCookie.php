<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use Pidgey\Http\SetCookie;
use SensitiveParameter;

/**
 * The `access_token` cookie (see `api/authentication/access-token.md#cookie`).
 *
 * Host-only (no `Domain`), with the same attributes in every environment.
 */
final class AccessTokenCookie
{
    public const NAME = 'access_token';

    private const PATH = '/';

    private const SAME_SITE = 'Strict';

    private const PAST_DATE = 'Thu, 01 Jan 1970 00:00:00 GMT';

    /**
     * Builds the cookie that carries the access token.
     *
     * @param string  $jwt The access token.
     * @param integer $ttl The `Max-Age`, in seconds: `ACCESS_TOKEN_TTL`.
     *
     * @return SetCookie
     */
    public static function set(#[SensitiveParameter] string $jwt, int $ttl): SetCookie
    {
        return new SetCookie(self::NAME, $jwt, path: self::PATH, maxAge: $ttl, sameSite: self::SAME_SITE);
    }

    /**
     * Builds the logout cookie: same attributes, an empty value, `Max-Age=0` and a fixed past date.
     *
     * @return SetCookie
     */
    public static function clear(): SetCookie
    {
        return new SetCookie(
            self::NAME,
            '',
            path: self::PATH,
            maxAge: 0,
            expires: self::PAST_DATE,
            sameSite: self::SAME_SITE,
        );
    }
}
