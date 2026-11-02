<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use LogicException;
use Pidgey\Http\BaseMiddleware;
use Pidgey\Http\ForbiddenException;
use Pidgey\Http\ProcessingRequest;

/**
 * Route middleware: the admin check of the admin routes, listed after Gate
 * (see `api/authentication/gate.md#admin-check`).
 *
 * Reads `isAdmin` as the gate read it from the database. Without the gate, require() throws
 * LogicException, which answers 500.
 */
final class AdminCheck extends BaseMiddleware
{
    public const FORBIDDEN_MESSAGE = 'Admin access required';

    /**
     * Rejects an authenticated user who is not the instance admin.
     *
     * @param ProcessingRequest $request The request being dispatched.
     *
     * @return void
     *
     * @throws ForbiddenException When the authenticated user is not an admin.
     * @throws LogicException     When no AuthenticatedUser is attached (the route lacks Gate).
     */
    public function processRequest(ProcessingRequest $request): void
    {
        if (!$request->require(AuthenticatedUser::class)->isAdmin) {
            throw new ForbiddenException(self::FORBIDDEN_MESSAGE);
        }
    }
}
