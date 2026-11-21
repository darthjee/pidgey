<?php

declare(strict_types=1);

namespace Pidgey\Repository;

use DateTimeImmutable;
use PDO;
use PDOException;
use Pidgey\Model\IdempotencyKey;

/**
 * Reads and writes the `idempotency_keys` table.
 *
 * Holds no idempotency rule: the flow, the TTL and the lease are decided by the caller. Every
 * query is scoped to an organization and every statement is a single autocommit statement.
 */
final class IdempotencyKeyRepository
{
    /** MySQL error number of a duplicate entry for a unique key. */
    private const DUPLICATE_ENTRY = 1062;

    /**
     * Creates the repository on the request's connection.
     *
     * @param PDO $pdo Connection to the application database.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Finds the row of a key, whatever its state and even when expired.
     *
     * @param integer $organizationId Organization the key belongs to.
     * @param string  $keyHash        Lowercase hex SHA-256 of the raw key.
     *
     * @return IdempotencyKey|null
     *
     * @throws PDOException When the statement fails.
     */
    public function find(int $organizationId, string $keyHash): ?IdempotencyKey
    {
        $statement = $this->pdo->prepare(
            'SELECT state, request_hash, response_status, response_body, lease_until, expires_at '
            . 'FROM idempotency_keys WHERE organization_id = :organization_id AND key_hash = :key_hash',
        );
        $statement->execute(['organization_id' => $organizationId, 'key_hash' => $keyHash]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : IdempotencyKey::fromRow($row);
    }

    /**
     * Reserves a key as `in_flight`: inserts the row, or overwrites the row of the same key when
     * it is expired at `$now`.
     *
     * Returns false, without throwing, when the key already has a row that is not expired (the
     * unique index decided the race).
     *
     * @param integer           $organizationId Organization the key belongs to.
     * @param string            $keyHash        Lowercase hex SHA-256 of the raw key.
     * @param string            $requestHash    Lowercase hex SHA-256 of the normalized payload.
     * @param DateTimeImmutable $now            Reservation time.
     * @param DateTimeImmutable $leaseUntil     End of the in-flight lease.
     * @param DateTimeImmutable $expiresAt      End of the key's retention.
     *
     * @return boolean True when this caller holds the reservation.
     *
     * @throws PDOException When a statement fails for any reason but a duplicate key.
     */
    public function reserve(
        int $organizationId,
        string $keyHash,
        string $requestHash,
        DateTimeImmutable $now,
        DateTimeImmutable $leaseUntil,
        DateTimeImmutable $expiresAt,
    ): bool {
        $values = [
            'organization_id' => $organizationId,
            'key_hash' => $keyHash,
            'request_hash' => $requestHash,
            'lease_until' => Sql::dateTime($leaseUntil),
            'expires_at' => Sql::dateTime($expiresAt),
        ];

        try {
            $this->pdo->prepare(
                'INSERT INTO idempotency_keys '
                . '(organization_id, key_hash, request_hash, state, lease_until, expires_at) '
                . "VALUES (:organization_id, :key_hash, :request_hash, 'in_flight', :lease_until, :expires_at)",
            )->execute($values);

            return true;
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) !== self::DUPLICATE_ENTRY) {
                throw $exception;
            }
        }

        $statement = $this->pdo->prepare(
            "UPDATE idempotency_keys SET request_hash = :request_hash, state = 'in_flight', "
            . 'response_status = NULL, response_body = NULL, lease_until = :lease_until, '
            . 'expires_at = :expires_at '
            . 'WHERE organization_id = :organization_id AND key_hash = :key_hash AND expires_at <= :now',
        );
        $statement->execute($values + ['now' => Sql::dateTime($now)]);

        return $statement->rowCount() === 1;
    }

    /**
     * Stores the answer of a key and marks it `completed`.
     *
     * @param integer $organizationId Organization the key belongs to.
     * @param string  $keyHash        Lowercase hex SHA-256 of the raw key.
     * @param integer $status         HTTP status of the answer.
     * @param string  $body           JSON body of the answer.
     *
     * @return void
     *
     * @throws PDOException When the statement fails.
     */
    public function complete(int $organizationId, string $keyHash, int $status, string $body): void
    {
        $this->pdo->prepare(
            "UPDATE idempotency_keys SET state = 'completed', response_status = :status, "
            . 'response_body = :body WHERE organization_id = :organization_id AND key_hash = :key_hash',
        )->execute([
            'organization_id' => $organizationId,
            'key_hash' => $keyHash,
            'status' => $status,
            'body' => $body,
        ]);
    }

    /**
     * Deletes the row of a key, so the same key can be used again.
     *
     * @param integer $organizationId Organization the key belongs to.
     * @param string  $keyHash        Lowercase hex SHA-256 of the raw key.
     *
     * @return void
     *
     * @throws PDOException When the statement fails.
     */
    public function release(int $organizationId, string $keyHash): void
    {
        $this->pdo->prepare(
            'DELETE FROM idempotency_keys WHERE organization_id = :organization_id AND key_hash = :key_hash',
        )->execute(['organization_id' => $organizationId, 'key_hash' => $keyHash]);
    }

    /**
     * Deletes at most `$limit` rows of the organization that are expired at `$now`.
     *
     * @param integer           $organizationId Organization whose rows are cleaned.
     * @param DateTimeImmutable $now            Reference time.
     * @param integer           $limit          Maximum number of rows to delete.
     *
     * @return integer Number of rows deleted.
     *
     * @throws PDOException When the statement fails.
     */
    public function deleteExpired(int $organizationId, DateTimeImmutable $now, int $limit): int
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM idempotency_keys WHERE organization_id = :organization_id AND expires_at <= :now '
            . 'LIMIT :limit',
        );
        $statement->bindValue('organization_id', $organizationId, PDO::PARAM_INT);
        $statement->bindValue('now', Sql::dateTime($now));
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount();
    }
}
