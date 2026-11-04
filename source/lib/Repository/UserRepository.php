<?php

declare(strict_types=1);

namespace Pidgey\Repository;

use DateTimeImmutable;
use LogicException;
use PDO;
use PDOException;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use SensitiveParameter;

/**
 * Reads and writes the `users` table.
 *
 * Values are stored and compared as given: trimming or normalizing a username or email is the
 * caller's job. Lookups by `username` and `email` are case- and accent-insensitive through the
 * columns' `utf8mb4_0900_ai_ci` collation (which is NO PAD: a trailing space is significant).
 */
final class UserRepository
{
    private const COLUMNS = 'id, organization_id, username, email, password_digest, is_admin, status, '
        . 'created_at, updated_at';

    /** COLUMNS qualified with the `u` alias, for joins. */
    private const JOINED_COLUMNS = 'u.id, u.organization_id, u.username, u.email, u.password_digest, u.is_admin, '
        . 'u.status, u.created_at, u.updated_at';

    /**
     * Creates the repository on the request's connection.
     *
     * @param PDO $pdo Connection to the application database.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Inserts a user and returns it, re-read so column defaults are reflected.
     *
     * @param integer    $organizationId Organization the user belongs to.
     * @param string     $username       Login name, stored as given.
     * @param string     $email          Recovery email address, stored as given.
     * @param string     $passwordDigest Digest of the password, from `password_hash()`.
     * @param UserStatus $status         Approval status (always explicit: the column has no default).
     * @param boolean    $isAdmin        Whether the user is the global instance admin.
     *
     * @return User
     *
     * @throws PDOException   When the statement fails (e.g. a duplicate username or email).
     * @throws LogicException When the inserted row cannot be read back.
     */
    public function create(
        int $organizationId,
        string $username,
        string $email,
        #[SensitiveParameter] string $passwordDigest,
        UserStatus $status,
        bool $isAdmin = false,
    ): User {
        $this->pdo->prepare(
            'INSERT INTO users (organization_id, username, email, password_digest, is_admin, status) '
            . 'VALUES (:organization_id, :username, :email, :password_digest, :is_admin, :status)',
        )->execute([
            'organization_id' => $organizationId,
            'username' => $username,
            'email' => $email,
            'password_digest' => $passwordDigest,
            'is_admin' => (int) $isAdmin,
            'status' => $status->value,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        return $this->findById($id)
            ?? throw new LogicException("User {$id} was inserted but cannot be read back.");
    }

    /**
     * Finds a user by id, in any organization.
     *
     * Unscoped on purpose: sessions resolve the user behind a token before any organization
     * is known (see persistence/repositories.md, "Organization scoping", and
     * api/authentication/sessions.md).
     *
     * @param integer $id Primary key.
     *
     * @return User|null Null when no user has this id.
     *
     * @throws PDOException When the statement fails.
     */
    public function findById(int $id): ?User
    {
        return $this->fetchOne('SELECT ' . self::COLUMNS . ' FROM users WHERE id = :id', ['id' => $id]);
    }

    /**
     * Finds a user by username, in any organization, regardless of case and accents.
     *
     * Unscoped on purpose: login identifies the user, and so the organization, by username
     * (see persistence/repositories.md, "Organization scoping", and api/endpoints/auth/session.md).
     *
     * @param string $username Username, compared as given (no trimming).
     *
     * @return User|null Null when no user has this username.
     *
     * @throws PDOException When the statement fails.
     */
    public function findByUsername(string $username): ?User
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE username = :username',
            ['username' => $username],
        );
    }

    /**
     * Finds a user by email, in any organization, regardless of case and accents.
     *
     * Unscoped on purpose: account recovery identifies the user by email before any session
     * exists (see persistence/repositories.md, "Organization scoping", and
     * api/endpoints/auth/recovery.md).
     *
     * @param string $email Email address, compared as given (no trimming).
     *
     * @return User|null Null when no user has this email.
     *
     * @throws PDOException When the statement fails.
     */
    public function findByEmail(string $email): ?User
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE email = :email',
            ['email' => $email],
        );
    }

    /**
     * Finds a user whose username or email is already taken, in one query (the register pre-check).
     *
     * When one row matches the username and another the email, the username row is returned,
     * so the caller can report `USERNAME_TAKEN` first. Every status counts: denied users keep
     * their username and email. Unscoped on purpose: usernames and emails are unique across
     * organizations (see api/endpoints/auth/session.md).
     *
     * @param string $username Username, compared as given (no trimming).
     * @param string $email    Email address, compared as given (no trimming).
     *
     * @return User|null Null when neither the username nor the email is taken.
     *
     * @throws PDOException When the statement fails.
     */
    public function findTaken(string $username, string $email): ?User
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE username = :username OR email = :email '
            . 'ORDER BY (username = :username_first) DESC LIMIT 1',
            ['username' => $username, 'email' => $email, 'username_first' => $username],
        );
    }

    /**
     * Stores a new password digest for a user (the login rehash).
     *
     * Unscoped on purpose: login identifies the user by username before any organization is known.
     *
     * @param integer $id     Primary key.
     * @param string  $digest New digest, from `password_hash()`.
     *
     * @return void
     *
     * @throws PDOException When the statement fails.
     */
    public function updatePasswordDigest(int $id, #[SensitiveParameter] string $digest): void
    {
        $this->pdo->prepare('UPDATE users SET password_digest = :password_digest WHERE id = :id')
            ->execute(['password_digest' => $digest, 'id' => $id]);
    }

    /**
     * Tells whether another user than the given one has this username, regardless of case and
     * accents (the account edit "taken" check).
     *
     * Unscoped on purpose: usernames are unique across organizations.
     *
     * @param string  $username Username, compared as given with the column collation.
     * @param integer $userId   The caller, whose own row is excluded.
     *
     * @return boolean
     *
     * @throws PDOException When the statement fails.
     */
    public function usernameTakenByOther(string $username, int $userId): bool
    {
        return $this->exists(
            'SELECT 1 FROM users WHERE username = :username AND id <> :id LIMIT 1',
            ['username' => $username, 'id' => $userId],
        );
    }

    /**
     * Tells whether another user than the given one has this email, regardless of case and
     * accents (the account edit "taken" check).
     *
     * Unscoped on purpose: emails are unique across organizations.
     *
     * @param string  $email  Email address, compared as given with the column collation.
     * @param integer $userId The caller, whose own row is excluded.
     *
     * @return boolean
     *
     * @throws PDOException When the statement fails.
     */
    public function emailTakenByOther(string $email, int $userId): bool
    {
        return $this->exists(
            'SELECT 1 FROM users WHERE email = :email AND id <> :id LIMIT 1',
            ['email' => $email, 'id' => $userId],
        );
    }

    /**
     * Stores a user's username, email and password digest in one statement (the account edit).
     *
     * Unscoped on purpose: the account edit acts on the session's own user.
     *
     * @param integer $id       Primary key.
     * @param string  $username Username, stored as given.
     * @param string  $email    Email address, stored as given.
     * @param string  $digest   Password digest, from `password_hash()`.
     *
     * @return void
     *
     * @throws PDOException When the statement fails (e.g. a duplicate username or email).
     */
    public function updateAccount(int $id, string $username, string $email, #[SensitiveParameter] string $digest): void
    {
        $this->pdo->prepare(
            'UPDATE users SET username = :username, email = :email, password_digest = :password_digest WHERE id = :id',
        )->execute(['username' => $username, 'email' => $email, 'password_digest' => $digest, 'id' => $id]);
    }

    /**
     * Finds a user by id within one organization.
     *
     * @param integer $organizationId Organization the user must belong to.
     * @param integer $id             Primary key.
     *
     * @return User|null Null when no user of this organization has this id.
     *
     * @throws PDOException When the statement fails.
     */
    public function findInOrganization(int $organizationId, int $id): ?User
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE organization_id = :organization_id AND id = :id',
            ['organization_id' => $organizationId, 'id' => $id],
        );
    }

    /**
     * Finds the approved user behind a live session, in one query (the gate's lookup).
     *
     * Joins `users` to `refresh_tokens`: the session must have an unrevoked, unexpired row
     * (rotated rows are revoked, so at most one row is live), its user must be `approved` and
     * must be the given user. Unscoped on purpose: the gate resolves the user, and so the
     * organization, from the token (see api/authentication/gate.md).
     *
     * @param integer           $userId      The token's `sub`.
     * @param string            $sessionUuid The token's `sid`.
     * @param DateTimeImmutable $now         Current time, from the WallClock (never the database's).
     *
     * @return User|null Null when no approved user of that id has a live session with this UUID.
     *
     * @throws PDOException When the statement fails.
     */
    public function findApprovedBySession(int $userId, string $sessionUuid, DateTimeImmutable $now): ?User
    {
        return $this->fetchOne(
            'SELECT ' . self::JOINED_COLUMNS . ' FROM users u '
            . 'JOIN refresh_tokens rt ON rt.user_id = u.id '
            . 'WHERE rt.session_uuid = :session_uuid AND rt.revoked_at IS NULL AND rt.expires_at > :now '
            . 'AND u.status = :status AND u.id = :id '
            . 'LIMIT 1',
            [
                'session_uuid' => $sessionUuid,
                'now' => Sql::dateTime($now),
                'status' => UserStatus::Approved->value,
                'id' => $userId,
            ],
        );
    }

    /**
     * Tells whether a query returns at least one row.
     *
     * @param string               $sql    Statement with named placeholders.
     * @param array<string, mixed> $params Placeholder name => value.
     *
     * @return boolean
     *
     * @throws PDOException When the statement fails.
     */
    private function exists(string $sql, array $params): bool
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Runs a single-row query and builds the user, if any.
     *
     * @param string               $sql    Statement with named placeholders.
     * @param array<string, mixed> $params Placeholder name => value.
     *
     * @return User|null
     *
     * @throws PDOException When the statement fails.
     */
    private function fetchOne(string $sql, array $params): ?User
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : User::fromRow($row);
    }
}
