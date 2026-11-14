<?php

declare(strict_types=1);

namespace Pidgey\Repository;

use DateTimeImmutable;
use LogicException;
use PDO;
use PDOException;
use Pidgey\Model\SmtpTestLimit;

/**
 * Reads and writes the `smtp_test_limits` table (one row per organization).
 *
 * Holds no limit rule: the window, the count and the lease are decided by the caller.
 */
final class SmtpTestLimitRepository
{
    /**
     * Creates the repository on the request's connection.
     *
     * @param PDO $pdo Connection to the application database.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Creates the organization's row when missing (a fresh window at `$now`, no attempts) and
     * returns it locked (`SELECT … FOR UPDATE`) until the transaction ends.
     *
     * Must run inside a transaction. The upsert is atomic, so two first attempts racing to
     * insert the row leave exactly one.
     *
     * @param integer           $organizationId Organization the row belongs to.
     * @param DateTimeImmutable $now            Start of the window of a newly created row.
     *
     * @return SmtpTestLimit
     *
     * @throws PDOException   When the statement fails (e.g. an unknown organization).
     * @throws LogicException When the row cannot be read back.
     */
    public function lockForOrganization(int $organizationId, DateTimeImmutable $now): SmtpTestLimit
    {
        $this->pdo->prepare(
            'INSERT INTO smtp_test_limits (organization_id, window_started_at, attempts) '
            . 'VALUES (:organization_id, :window_started_at, 0) '
            . 'ON DUPLICATE KEY UPDATE organization_id = organization_id',
        )->execute(['organization_id' => $organizationId, 'window_started_at' => Sql::dateTime($now)]);

        $statement = $this->pdo->prepare(
            'SELECT window_started_at, attempts, busy_until FROM smtp_test_limits '
            . 'WHERE organization_id = :organization_id FOR UPDATE',
        );
        $statement->execute(['organization_id' => $organizationId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new LogicException("Organization {$organizationId} has no smtp test limit row.");
        }

        return SmtpTestLimit::fromRow($row);
    }

    /**
     * Writes the window, the count and the lease of an organization.
     *
     * @param integer           $organizationId  Organization the row belongs to.
     * @param DateTimeImmutable $windowStartedAt Start of the window.
     * @param integer           $attempts        Count in the window, including this attempt.
     * @param DateTimeImmutable $busyUntil       End of the lease.
     *
     * @return void
     *
     * @throws PDOException When the statement fails.
     */
    public function takeAttempt(
        int $organizationId,
        DateTimeImmutable $windowStartedAt,
        int $attempts,
        DateTimeImmutable $busyUntil,
    ): void {
        $this->pdo->prepare(
            'UPDATE smtp_test_limits SET window_started_at = :window_started_at, attempts = :attempts, '
            . 'busy_until = :busy_until WHERE organization_id = :organization_id',
        )->execute([
            'organization_id' => $organizationId,
            'window_started_at' => Sql::dateTime($windowStartedAt),
            'attempts' => $attempts,
            'busy_until' => Sql::dateTime($busyUntil),
        ]);
    }

    /**
     * Clears the lease, only when it is still the one given (compare-and-clear), so a newer
     * lease taken after this one expired is left alone.
     *
     * A single autocommit statement.
     *
     * @param integer           $organizationId Organization the row belongs to.
     * @param DateTimeImmutable $busyUntil      The lease end this holder set.
     *
     * @return void
     *
     * @throws PDOException When the statement fails.
     */
    public function releaseLease(int $organizationId, DateTimeImmutable $busyUntil): void
    {
        $this->pdo->prepare(
            'UPDATE smtp_test_limits SET busy_until = NULL '
            . 'WHERE organization_id = :organization_id AND busy_until = :busy_until',
        )->execute(['organization_id' => $organizationId, 'busy_until' => Sql::dateTime($busyUntil)]);
    }
}
