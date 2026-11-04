<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use Pidgey\Auth\AccessTokenCookie;
use Pidgey\Auth\Sessions;
use Pidgey\Container;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Http\Validator;

/**
 * `DELETE /auth/logoff.json` (public, ungated): ends the whole session of the presented refresh
 * token (see `api/endpoints/auth/session.md#delete-authlogoffjson`).
 *
 * Once the body is valid, the answer is always `204` with an empty body and the `access_token`
 * cookie cleared, whether or not the token was known. The request body is never logged.
 */
final class LogoffHandler extends RequestHandler
{
    /**
     * Builds the handler.
     *
     * @param Sessions $sessions Revokes the session's active tokens.
     */
    public function __construct(private readonly Sessions $sessions)
    {
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
        return new self($container->sessions());
    }

    /**
     * Validates the body, logs the session out, then answers `204` and clears the cookie.
     *
     * @param ProcessingRequest $request The request being dispatched.
     *
     * @return Response
     *
     * @throws ValidationFailedException When `refreshToken` is missing, empty or not a string.
     */
    protected function process(ProcessingRequest $request): Response
    {
        $validator = new Validator($request->request()->json());
        $validator->string('refreshToken')->notEmpty();
        $fields = $validator->validated();

        $this->sessions->logout($fields['refreshToken']);

        $response = new Response('', 204);
        AccessTokenCookie::clear()->applyTo($response);

        return $response;
    }
}
