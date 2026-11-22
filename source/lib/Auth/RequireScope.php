<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use LogicException;
use Pidgey\Http\BaseMiddleware;
use Pidgey\Http\ForbiddenException;
use Pidgey\Http\ProcessingRequest;

/**
 * Route middleware: the scope check of the `/v1` routes, listed after ApiTokenAuth
 * (see `api/authentication/api-tokens.md#scopes`).
 *
 * One subclass per scope, each setting SCOPE. Without ApiTokenAuth, require() throws
 * LogicException, which answers 500.
 */
abstract class RequireScope extends BaseMiddleware
{
    public const FORBIDDEN_MESSAGE = 'The API token lacks the required scope';

    /** The scope the token must have; set by each subclass. */
    protected const SCOPE = '';

    /**
     * Rejects an authenticated token that does not hold the scope.
     *
     * @param ProcessingRequest $request The request being dispatched.
     *
     * @return void
     *
     * @throws ForbiddenException When the token does not have the scope.
     * @throws LogicException     When no AuthenticatedApiToken is attached (the route lacks ApiTokenAuth).
     */
    public function processRequest(ProcessingRequest $request): void
    {
        if (!$request->require(AuthenticatedApiToken::class)->hasScope(static::SCOPE)) {
            throw new ForbiddenException(self::FORBIDDEN_MESSAGE);
        }
    }
}
