<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use Pidgey\Auth\AccountEdit;
use Pidgey\Auth\AuthenticatedUser;
use Pidgey\Auth\Passwords;
use Pidgey\Container;
use Pidgey\Http\BadRequestException;
use Pidgey\Http\HttpException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Http\Validator;

/**
 * `PATCH /auth/account.json` (gated): the caller edits their own username, email and/or
 * password, proving the current password (see `api/endpoints/auth/account.md`).
 *
 * Validates the body and rejects an edit with nothing to change (not a failed attempt); the
 * lockout, the checks and the update are the AccountEdit service's. The request body is never
 * logged.
 */
final class AccountHandler extends RequestHandler
{
    public const NOTHING_TO_CHANGE = 'At least one of username, email, or newPassword is required';

    private const MAX_CHARACTERS = 255;

    private const CHANGE_FIELDS = ['username', 'email', 'newPassword'];

    /**
     * Builds the handler.
     *
     * @param AccountEdit $accountEdit Runs the edit with its lockout.
     */
    public function __construct(private readonly AccountEdit $accountEdit)
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
        return new self($container->accountEdit());
    }

    /**
     * Validates the body, edits the caller's account, then answers `200` with the new values.
     *
     * @param ProcessingRequest $request The request being dispatched, with its AuthenticatedUser.
     *
     * @return Response
     *
     * @throws ValidationFailedException When a field is invalid (every error at once).
     * @throws BadRequestException       When nothing is to change, or the current password is wrong.
     * @throws HttpException             When the edit is refused (`401`, `409`, `423`).
     */
    protected function process(ProcessingRequest $request): Response
    {
        $validator = new Validator($request->request()->json());
        $validator->string('currentPassword')->notEmpty();
        $validator->string('username')->optional()->trim()->notEmpty()->maxLength(self::MAX_CHARACTERS);
        $validator->string('email')->optional()->trim()->email()->maxLength(self::MAX_CHARACTERS);
        $validator->string('newPassword')
            ->optional()
            ->minLength(Passwords::MIN_CHARACTERS)
            ->maxBytes(Passwords::MAX_BYTES)
            ->noNulByte();
        $fields = $validator->validated();

        if (array_intersect_key($fields, array_flip(self::CHANGE_FIELDS)) === []) {
            throw new BadRequestException(self::NOTHING_TO_CHANGE);
        }

        $result = $this->accountEdit->edit(
            $request->require(AuthenticatedUser::class)->id,
            $fields['currentPassword'],
            $fields['username'] ?? null,
            $fields['email'] ?? null,
            $fields['newPassword'] ?? null,
        );

        return new JsonResponse(['username' => $result->username, 'email' => $result->email], 200);
    }
}
