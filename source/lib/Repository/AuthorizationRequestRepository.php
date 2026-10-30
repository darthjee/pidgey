<?php

declare(strict_types=1);

namespace Pidgey\Repository;

use DateTimeImmutable;
use LogicException;
use PDO;
use PDOException;
use Pidgey\Model\AuthorizationRequest;

/**
 * Reads and writes the `authorization_requests` table.
 *
 * Stores and looks up the SHA-256 hex digest of each poll token, never the plain token:
 * hashing happens in the service, before the call.
 */
final class AuthorizationRequestRepository
{
    private const COLUMNS = 'id, uuid, username, user_id, status, poll_token_hash, request_ip, '
        . 'request_user_agent, approved_by_user_id, created_at, expires_at, resolved_at, logged_at, '
        . 'authorize_failed_attempts, authorize_locked_until';

    /**
     * Creates the repository on the request's connection.
     *
     * @param PDO $pdo Connection to the application database.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Inserts an open request and returns it, re-read so column defaults are reflected.
     *
     * The status (`open`) and the failed-attempts counter (`0`) come from the column defaults.
     *
     * @param string            $uuid             Public id of the request.
     * @param string            $username         Username as typed by the requester.
     * @param integer|null      $userId           Resolved user; null when the username does not exist.
     * @param string            $pollTokenHash    SHA-256 hex digest of the requester's poll token.
     * @param string            $requestIp        Requester's IP address.
     * @param string            $requestUserAgent Requester's user agent.
     * @param DateTimeImmutable $createdAt        Creation time, from `WallClock` (rate limits).
     * @param DateTimeImmutable $expiresAt        End of the request's lifetime.
     *
     * @return AuthorizationRequest
     *
     * @throws PDOException   When the statement fails (e.g. a duplicate uuid or digest).
     * @throws LogicException When the inserted row cannot be read back.
     */
    public function create(
        string $uuid,
        string $username,
        ?int $userId,
        string $pollTokenHash,
        string $requestIp,
        string $requestUserAgent,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
    ): AuthorizationRequest {
        $this->pdo->prepare(
            'INSERT INTO authorization_requests (uuid, username, user_id, poll_token_hash, request_ip, '
            . 'request_user_agent, created_at, expires_at) '
            . 'VALUES (:uuid, :username, :user_id, :poll_token_hash, :request_ip, '
            . ':request_user_agent, :created_at, :expires_at)',
        )->execute([
            'uuid' => $uuid,
            'username' => $username,
            'user_id' => $userId,
            'poll_token_hash' => $pollTokenHash,
            'request_ip' => $requestIp,
            'request_user_agent' => $requestUserAgent,
            'created_at' => Sql::dateTime($createdAt),
            'expires_at' => Sql::dateTime($expiresAt),
        ]);
        $id = (int) $this->pdo->lastInsertId();

        return $this->findById($id)
            ?? throw new LogicException("Authorization request {$id} was inserted but cannot be read back.");
    }

    /**
     * Finds a request by id.
     *
     * Unscoped on purpose: the requester has no session, so no organization is known (see
     * api/endpoints/auth/device-authorization.md).
     *
     * @param integer $id Primary key.
     *
     * @return AuthorizationRequest|null Null when no request has this id.
     *
     * @throws PDOException When the statement fails.
     */
    public function findById(int $id): ?AuthorizationRequest
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM authorization_requests WHERE id = :id',
            ['id' => $id],
        );
    }

    /**
     * Finds a request by its public id.
     *
     * Unscoped on purpose: the approver addresses the request by uuid; ownership is checked by
     * the service (see api/endpoints/auth/device-authorization.md).
     *
     * @param string $uuid Public id of the request.
     *
     * @return AuthorizationRequest|null Null when no request has this uuid.
     *
     * @throws PDOException When the statement fails.
     */
    public function findByUuid(string $uuid): ?AuthorizationRequest
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM authorization_requests WHERE uuid = :uuid',
            ['uuid' => $uuid],
        );
    }

    /**
     * Finds a request by the digest of the poll token the requester presented.
     *
     * Unscoped on purpose: the requester polls and claims with its poll token alone (see
     * api/endpoints/auth/device-authorization.md).
     *
     * @param string $pollTokenHash SHA-256 hex digest of the presented poll token.
     *
     * @return AuthorizationRequest|null Null when no request has this digest.
     *
     * @throws PDOException When the statement fails.
     */
    public function findByPollTokenHash(string $pollTokenHash): ?AuthorizationRequest
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM authorization_requests WHERE poll_token_hash = :poll_token_hash',
            ['poll_token_hash' => $pollTokenHash],
        );
    }

    /**
     * Runs a single-row query and builds the request, if any.
     *
     * @param string               $sql    Statement with named placeholders.
     * @param array<string, mixed> $params Placeholder name => value.
     *
     * @return AuthorizationRequest|null
     *
     * @throws PDOException When the statement fails.
     */
    private function fetchOne(string $sql, array $params): ?AuthorizationRequest
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : AuthorizationRequest::fromRow($row);
    }
}
