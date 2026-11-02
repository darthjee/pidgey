<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use Pidgey\Model\User;

/**
 * The shared `user` object of the login, register, refresh and device-poll bodies
 * (see `api/authentication/gate.md#shared-user-payload`).
 *
 * Never carries the status, the password digest, the organization or any token; each endpoint
 * composes the rest of its body.
 */
final class UserPayload
{
    /**
     * Builds the `user` object of a user.
     *
     * @param User $user The user.
     *
     * @return array{id: integer, username: string, email: string, isAdmin: boolean}
     */
    public static function fromUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'isAdmin' => $user->isAdmin,
        ];
    }
}
