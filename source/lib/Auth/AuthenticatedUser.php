<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use Pidgey\Model\User;

/**
 * The user authenticated by the gate on the cookie surface (see `api/authentication/gate.md`).
 *
 * `username`, `isAdmin` and `organizationId` come from the database row, never from the JWT.
 */
final readonly class AuthenticatedUser implements Caller
{
    /**
     * Builds the authenticated user.
     *
     * @param integer $id             The user id (the token's `sub`).
     * @param string  $sessionId      The session UUID (the token's `sid`).
     * @param string  $username       The username, from the database.
     * @param boolean $isAdmin        Whether the user is the instance admin, from the database.
     * @param integer $organizationId The user's organization, from the database.
     */
    public function __construct(
        public int $id,
        public string $sessionId,
        public string $username,
        public bool $isAdmin,
        public int $organizationId,
    ) {
    }

    /**
     * Builds the authenticated user from the database row and the session UUID.
     *
     * @param User   $user      The user row found by the gate.
     * @param string $sessionId The session UUID (the token's `sid`).
     *
     * @return self
     */
    public static function fromUser(User $user, string $sessionId): self
    {
        return new self($user->id, $sessionId, $user->username, $user->isAdmin, $user->organizationId);
    }

    /**
     * Returns the user's organization.
     *
     * @return integer
     */
    public function organizationId(): int
    {
        return $this->organizationId;
    }
}
