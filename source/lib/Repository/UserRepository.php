<?php

declare(strict_types=1);

namespace Pidgey\Repository;

use LogicException;
use PDO;
use PDOException;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;

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
        string $passwordDigest,
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
