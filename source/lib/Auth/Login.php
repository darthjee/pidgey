<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use PDOException;
use Pidgey\Http\ForbiddenException;
use Pidgey\Http\UnauthorizedException;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\UserRepository;
use Random\RandomException;
use SensitiveParameter;

/**
 * Logs a user in by username and starts a new session
 * (see `api/endpoints/auth/session.md#post-authloginjson`).
 *
 * Enumeration-safe: exactly one `password_verify()` runs whether or not the username exists,
 * and an unknown username and a wrong password give the same `401`. The status is checked only
 * after a successful verify, so it cannot be probed without the password. Nothing is logged.
 */
final class Login
{
    private const INVALID_CREDENTIALS = 'Invalid username or password';

    /**
     * Builds the service.
     *
     * @param UserRepository $users     The `users` repository.
     * @param Sessions       $sessions  Starts the new session.
     * @param Passwords      $passwords Verifies and rehashes the password.
     */
    public function __construct(
        private readonly UserRepository $users,
        private readonly Sessions $sessions,
        private readonly Passwords $passwords,
    ) {
    }

    /**
     * Checks the credentials, rehashes an outdated digest, then starts a new session.
     *
     * @param string $username Trimmed, validated username (compared through the collation).
     * @param string $password Validated password, never trimmed.
     *
     * @return SessionTokens
     *
     * @throws UnauthorizedException When the username is unknown or the password is wrong.
     * @throws ForbiddenException    When the password is right but the user is `denied` (`USER_DENIED`).
     * @throws PDOException          When a statement fails.
     * @throws RandomException       When no source of randomness is available.
     */
    public function login(string $username, #[SensitiveParameter] string $password): SessionTokens
    {
        $user = $this->users->findByUsername($username);

        if (!$this->passwords->verify($password, $user?->passwordDigest) || $user === null) {
            throw new UnauthorizedException(self::INVALID_CREDENTIALS);
        }

        if ($user->status === UserStatus::Denied) {
            throw new ForbiddenException('This account has been denied access', 'USER_DENIED');
        }

        if ($this->passwords->needsRehash($user->passwordDigest)) {
            $this->users->updatePasswordDigest($user->id, $this->passwords->hash($password));
        }

        return $this->sessions->start($user);
    }
}
