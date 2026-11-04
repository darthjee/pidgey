<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use Pidgey\Auth\AccessToken;
use Pidgey\Auth\Login;
use Pidgey\Auth\SessionResponse;
use Pidgey\Container;
use Pidgey\Http\ForbiddenException;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use Pidgey\Http\UnauthorizedException;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Http\Validator;

/**
 * `POST /auth/login.json` (public): logs a user in by username and starts a new session
 * (see `api/endpoints/auth/session.md#post-authloginjson`).
 *
 * The request body is never logged.
 */
final class LoginHandler extends RequestHandler
{
    /**
     * Builds the handler.
     *
     * @param Login       $login       Checks the credentials and starts the session.
     * @param AccessToken $accessToken Gives the `access_token` cookie `Max-Age`.
     */
    public function __construct(
        private readonly Login $login,
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
        return new self($container->login(), $container->accessToken());
    }

    /**
     * Validates the body, logs in, then answers `201` with the session.
     *
     * @param ProcessingRequest $request The request being dispatched.
     *
     * @return Response
     *
     * @throws ValidationFailedException When a field is invalid (every error at once).
     * @throws UnauthorizedException     When the username is unknown or the password is wrong.
     * @throws ForbiddenException        When the user is denied.
     */
    protected function process(ProcessingRequest $request): Response
    {
        $validator = new Validator($request->request()->json());
        $validator->string('username')->trim()->notEmpty();
        $validator->string('password')->notEmpty();
        $fields = $validator->validated();

        $tokens = $this->login->login($fields['username'], $fields['password']);

        return SessionResponse::created($tokens, $this->accessToken->ttl());
    }
}
