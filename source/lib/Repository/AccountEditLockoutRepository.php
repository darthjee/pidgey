<?php

declare(strict_types=1);

namespace Pidgey\Repository;

use DateTimeImmutable;
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
     * Creates the user's lockout row when missing, leaving an existing row unchanged.
     *
     * An atomic upsert, so two first attempts racing to insert the row are both covered.
     *
     * @param integer $userId User the counter belongs to.
     *
     * @return void
     *
     * @throws PDOException When the statement fails (e.g. an unknown user).
     */
    public function ensure(int $userId): void
    {
        $this->pdo->prepare(
            'INSERT INTO account_edit_lockouts (user_id) VALUES (:user_id) '
            . 'ON DUPLICATE KEY UPDATE user_id = user_id',
        )->execute(['user_id' => $userId]);
    }

    /**
     * Reads the user's lockout row and locks it (`SELECT … FOR UPDATE`) until the transaction ends.
     *
     * Must run inside a transaction, after ensure().
     *
     * @param integer $userId User the counter belongs to.
     *
     * @return AccountEditLockout
     *
     * @throws PDOException   When the statement fails.
     * @throws LogicException When the user has no lockout row.
     */
    public function lockForUpdate(int $userId): AccountEditLockout
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM account_edit_lockouts WHERE user_id = :user_id FOR UPDATE',
            ['user_id' => $userId],
        ) ?? throw new LogicException("User {$userId} has no account edit lockout row.");
    }

    /**
     * Resets the user's counter and lock (`failed_attempts = 0`, `locked_until = NULL`).
     *
     * @param integer $userId User the counter belongs to.
     *
     * @return void
     *
     * @throws PDOException When the statement fails.
     */
    public function clear(int $userId): void
    {
        $this->pdo->prepare(
            'UPDATE account_edit_lockouts SET failed_attempts = 0, locked_until = NULL WHERE user_id = :user_id',
        )->execute(['user_id' => $userId]);
    }

    /**
     * Counts one failed attempt with an atomic upsert and locks the row when the incremented
     * counter reaches the threshold (`>=`, so a counter already above it still locks).
     *
     * `locked_until` is assigned before `failed_attempts` in the same statement, so the
     * comparison reads the pre-increment value plus one, i.e. the incremented value.
     *
     * @param integer           $userId      User the counter belongs to.
     * @param integer           $maxAttempts Threshold (`ACCOUNT_EDIT_MAX_ATTEMPTS`).
     * @param DateTimeImmutable $lockUntil   Lock end set when the threshold is reached.
     *
     * @return AccountEditLockout The row after the increment.
     *
     * @throws PDOException   When the statement fails.
     * @throws LogicException When the row cannot be read back.
     */
    public function recordFailure(int $userId, int $maxAttempts, DateTimeImmutable $lockUntil): AccountEditLockout
    {
        $lockUntilValue = Sql::dateTime($lockUntil);
        $this->pdo->prepare(
            'INSERT INTO account_edit_lockouts (user_id, failed_attempts, locked_until) '
            . 'VALUES (:user_id, 1, IF(1 >= :insert_max, :insert_lock_until, NULL)) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'locked_until = IF(failed_attempts + 1 >= :update_max, :update_lock_until, locked_until), '
            . 'failed_attempts = failed_attempts + 1',
        )->execute([
            'user_id' => $userId,
            'insert_max' => $maxAttempts,
            'insert_lock_until' => $lockUntilValue,
            'update_max' => $maxAttempts,
            'update_lock_until' => $lockUntilValue,
        ]);

        return $this->findByUserId($userId)
            ?? throw new LogicException("User {$userId} has no account edit lockout row.");
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
