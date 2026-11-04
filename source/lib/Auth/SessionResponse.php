<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use Pidgey\Http\JsonResponse;

/**
 * The `201` answer of every endpoint that starts or continues a session (register, login,
 * refresh, device poll): the shared `user` payload, the refresh token and the `access_token`
 * cookie (see `api/endpoints/auth/session.md`).
 */
final class SessionResponse
{
    /**
     * Builds the response of a session.
     *
     * @param SessionTokens $tokens    The session's tokens and user.
     * @param integer       $accessTtl The cookie `Max-Age`: `ACCESS_TOKEN_TTL`, in seconds.
     *
     * @return JsonResponse
     */
    public static function created(SessionTokens $tokens, int $accessTtl): JsonResponse
    {
        $response = new JsonResponse(
            [
                'user' => UserPayload::fromUser($tokens->user),
                'refreshToken' => $tokens->refreshToken(),
            ],
            201,
        );
        AccessTokenCookie::set($tokens->accessToken(), $accessTtl)->applyTo($response);

        return $response;
    }
}
