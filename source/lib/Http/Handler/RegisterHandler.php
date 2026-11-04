<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use Pidgey\Auth\AccessToken;
use Pidgey\Auth\Passwords;
use Pidgey\Auth\Registration;
use Pidgey\Auth\SessionResponse;
use Pidgey\Container;
use Pidgey\Http\ConflictException;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Http\Validator;

/**
 * `POST /auth/register.json` (public): creates an organization with its first user and logs
 * the user in (see `api/endpoints/auth/session.md#post-authregisterjson`).
 *
 * The request body is never logged.
 */
final class RegisterHandler extends RequestHandler
{
    private const MAX_CHARACTERS = 255;

    private const PASSWORD_MIN_CHARACTERS = 8;

    private const PASSWORD_MAX_BYTES = Passwords::MAX_BYTES;

    /**
     * Builds the handler.
     *
     * @param Registration $registration Creates the organization, the user and the session.
     * @param AccessToken  $accessToken  Gives the `access_token` cookie `Max-Age`.
     */
    public function __construct(
        private readonly Registration $registration,
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
        return new self($container->registration(), $container->accessToken());
    }

    /**
     * Validates the body, registers, then answers `201` with the session.
     *
     * @param ProcessingRequest $request The request being dispatched.
     *
     * @return Response
     *
     * @throws ValidationFailedException When a field is invalid (every error at once).
     * @throws ConflictException         When the username or the email is taken.
     */
    protected function process(ProcessingRequest $request): Response
    {
        $validator = new Validator($request->request()->json());
        $validator->string('username')->trim()->notEmpty()->maxLength(self::MAX_CHARACTERS);
        $validator->string('email')->trim()->email()->maxLength(self::MAX_CHARACTERS);
        $validator->string('password')
            ->minLength(self::PASSWORD_MIN_CHARACTERS)
            ->maxBytes(self::PASSWORD_MAX_BYTES)
            ->noNulByte();
        $validator->string('organizationName')->trim()->notEmpty()->maxLength(self::MAX_CHARACTERS);
        $fields = $validator->validated();

        $tokens = $this->registration->register(
            $fields['username'],
            $fields['email'],
            $fields['password'],
            $fields['organizationName'],
        );

        return SessionResponse::created($tokens, $this->accessToken->ttl());
    }
}
