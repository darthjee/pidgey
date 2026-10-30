<?php

declare(strict_types=1);

namespace Pidgey\Repository;

use LogicException;
use PDO;
use PDOException;
use Pidgey\Model\AccountEditLockout;

/**
 * Reads and writes the `account_edit_lockouts` table (one row per user).
 *
 * Holds no lockout rule: thresholds and lock times are decided by the service.
 */
final class AccountEditLockoutRepository
{
    private const COLUMNS = 'id, user_id, failed_attempts, locked_until, created_at, updated_at';

    /**
     * Creates the repository on the request's connection.
     *
     * @param PDO $pdo Connection to the application database.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Inserts a user's lockout row and returns it, re-read so column defaults are reflected.
     *
     * The counter (`0`), the lock (null) and both timestamps come from the column defaults.
     *
     * @param integer $userId User the counter belongs to.
     *
     * @return AccountEditLockout
     *
     * @throws PDOException   When the statement fails (e.g. the user already has a row).
     * @throws LogicException When the inserted row cannot be read back.
     */
    public function create(int $userId): AccountEditLockout
    {
        $this->pdo->prepare('INSERT INTO account_edit_lockouts (user_id) VALUES (:user_id)')
            ->execute(['user_id' => $userId]);
        $id = (int) $this->pdo->lastInsertId();

        return $this->findById($id)
            ?? throw new LogicException("Account edit lockout {$id} was inserted but cannot be read back.");
    }

    /**
     * Finds a lockout row by id.
     *
     * Unscoped on purpose: the row belongs to a user, and the account edit acts on the
     * session's own user (see api/endpoints/auth/account.md).
     *
     * @param integer $id Primary key.
     *
     * @return AccountEditLockout|null Null when no lockout row has this id.
     *
     * @throws PDOException When the statement fails.
     */
    public function findById(int $id): ?AccountEditLockout
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM account_edit_lockouts WHERE id = :id',
            ['id' => $id],
        );
    }

    /**
     * Finds the lockout row of a user.
     *
     * Unscoped on purpose: the account edit acts on the session's own user (see
     * api/endpoints/auth/account.md).
     *
     * @param integer $userId User the counter belongs to.
     *
     * @return AccountEditLockout|null Null when the user has no lockout row yet.
     *
     * @throws PDOException When the statement fails.
     */
    public function findByUserId(int $userId): ?AccountEditLockout
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM account_edit_lockouts WHERE user_id = :user_id',
            ['user_id' => $userId],
        );
    }

    /**
     * Runs a single-row query and builds the lockout row, if any.
     *
     * @param string               $sql    Statement with named placeholders.
     * @param array<string, mixed> $params Placeholder name => value.
     *
     * @return AccountEditLockout|null
     *
     * @throws PDOException When the statement fails.
     */
    private function fetchOne(string $sql, array $params): ?AccountEditLockout
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : AccountEditLockout::fromRow($row);
    }
}
