<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use Pidgey\Auth\AccessToken;
use Pidgey\Auth\SessionResponse;
use Pidgey\Auth\Sessions;
use Pidgey\Container;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use Pidgey\Http\UnauthorizedException;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Http\Validator;

/**
 * `POST /auth/refresh.json` (public, ungated): rotates the presented refresh token and
 * continues the session (see `api/endpoints/auth/session.md#post-authrefreshjson`).
 *
 * Every failure answers the same `401` message, so the answer never reveals which check failed;
 * the replay and denied-user revocations are already committed by `Sessions::refresh()`. The
 * request body is never logged.
 */
final class RefreshHandler extends RequestHandler
{
    /** The single message of every refresh failure. */
    public const INVALID_TOKEN_MESSAGE = 'Invalid or expired refresh token';

    /**
     * Builds the handler.
     *
     * @param Sessions    $sessions    Rotates the refresh token.
     * @param AccessToken $accessToken Gives the `access_token` cookie `Max-Age`.
     */
    public function __construct(
        private readonly Sessions $sessions,
        private readonly AccessToken $accessToken,
    ) {
    }

    /**
     * Builds the handler from the request's container.
     *
     * @param Container $container The request's container.
     *
     * @return static
     */
    public static function build(Container $container): static
    {
        return new self($container->sessions(), $container->accessToken());
    }

    /**
     * Validates the body, rotates the token, then answers `201` with the session.
     *
     * @param ProcessingRequest $request The request being dispatched.
     *
     * @return Response
     *
     * @throws ValidationFailedException When `refreshToken` is missing, empty or not a string.
     * @throws UnauthorizedException     When the token cannot be refreshed, whatever the reason.
     */
    protected function process(ProcessingRequest $request): Response
    {
        $validator = new Validator($request->request()->json());
        $validator->string('refreshToken')->notEmpty();
        $fields = $validator->validated();

        $tokens = $this->sessions->refresh($fields['refreshToken']);

        if ($tokens === null) {
            throw new UnauthorizedException(self::INVALID_TOKEN_MESSAGE);
        }

        return SessionResponse::created($tokens, $this->accessToken->ttl());
    }
}
