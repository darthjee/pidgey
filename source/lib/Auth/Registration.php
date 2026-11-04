<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use LogicException;
use PDOException;
use Pidgey\Http\ConflictException;
use Pidgey\Infra\Transaction;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\OrganizationRepository;
use Pidgey\Repository\UserRepository;
use Random\RandomException;
use SensitiveParameter;

/**
 * Registers an organization with its first user and starts the user's session
 * (see `api/endpoints/auth/session.md#post-authregisterjson`).
 *
 * Register is the documented exception to enumeration safety: a taken username or email is
 * reported as such. Nothing here is logged.
 */
final class Registration
{
    /** SQLSTATE of an integrity constraint violation (e.g. a duplicate unique key). */
    private const INTEGRITY_VIOLATION = '23000';

    /**
     * Builds the service.
     *
     * @param Transaction            $transaction      Runs the inserts and the session start atomically.
     * @param OrganizationRepository $organizations    The `organizations` repository.
     * @param UserRepository         $users            The `users` repository.
     * @param Sessions               $sessions         Starts the new user's session.
     * @param Passwords              $passwords        Hashes the password.
     * @param boolean                $approvalRequired Whether new users start `pending` (`USER_APPROVAL_REQUIRED`).
     */
    public function __construct(
        private readonly Transaction $transaction,
        private readonly OrganizationRepository $organizations,
        private readonly UserRepository $users,
        private readonly Sessions $sessions,
        private readonly Passwords $passwords,
        private readonly bool $approvalRequired,
    ) {
    }

    /**
     * Checks that the username and email are free, hashes the password, then creates the
     * organization, the user and the session.
     *
     * @param string $username         Trimmed, validated username.
     * @param string $email            Trimmed, validated email.
     * @param string $password         Validated password, never trimmed.
     * @param string $organizationName Trimmed, validated organization name.
     *
     * @return SessionTokens
     *
     * @throws ConflictException When the username (`USERNAME_TAKEN`) or email (`EMAIL_TAKEN`) is taken.
     * @throws PDOException      When a statement fails for another reason.
     * @throws RandomException   When no source of randomness is available.
     */
    public function register(
        string $username,
        string $email,
        #[SensitiveParameter] string $password,
        string $organizationName,
    ): SessionTokens {
        $this->precheck($username, $email);

        return $this->create($username, $email, $this->passwords->hash($password), $organizationName);
    }

    /**
     * Creates the organization, the user and the session in one transaction.
     *
     * A unique-index violation on the user insert (a concurrent register took the username or
     * email after the pre-check) rolls the whole transaction back, organization included, and is
     * answered with the same `409` as the pre-check. Public so the race can be tested by skipping
     * the pre-check.
     *
     * @param string $username         Trimmed, validated username.
     * @param string $email            Trimmed, validated email.
     * @param string $digest           Digest of the password, from `Passwords::hash()`.
     * @param string $organizationName Trimmed, validated organization name.
     *
     * @return SessionTokens
     *
     * @throws ConflictException When the username (`USERNAME_TAKEN`) or email (`EMAIL_TAKEN`) is taken.
     * @throws PDOException      When a statement fails for another reason.
     * @throws LogicException    When a transaction is already active on the connection.
     * @throws RandomException   When no source of randomness is available.
     */
    public function create(
        string $username,
        string $email,
        #[SensitiveParameter] string $digest,
        string $organizationName,
    ): SessionTokens {
        try {
            return $this->transaction->run(function () use ($username, $email, $digest, $organizationName) {
                $organization = $this->organizations->create($organizationName);
                $user = $this->users->create($organization->id, $username, $email, $digest, $this->status());

                return $this->sessions->start($user);
            });
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() !== self::INTEGRITY_VIOLATION) {
                throw $exception;
            }

            $this->precheck($username, $email);

            throw $exception;
        }
    }

    /**
     * Throws the matching `409` when the username or the email is taken, the username first.
     *
     * One query finds a taken row; only on a conflict, a username lookup tells which field it is
     * (the comparison must follow the collation, so it is not done in PHP).
     *
     * @param string $username Trimmed username.
     * @param string $email    Trimmed email.
     *
     * @return void
     *
     * @throws ConflictException When the username or the email is taken.
     * @throws PDOException      When a statement fails.
     */
    private function precheck(string $username, string $email): void
    {
        if ($this->users->findTaken($username, $email) === null) {
            return;
        }

        if ($this->users->findByUsername($username) !== null) {
            throw new ConflictException('username is not available', 'USERNAME_TAKEN');
        }

        throw new ConflictException('email is not available', 'EMAIL_TAKEN');
    }

    /**
     * Returns the status of a new user: `approved` only when approval is not required.
     *
     * @return UserStatus
     */
    private function status(): UserStatus
    {
        return $this->approvalRequired ? UserStatus::Pending : UserStatus::Approved;
    }
}
