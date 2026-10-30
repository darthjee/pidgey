<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use PDO;

/**
 * Creates rows for integration tests with plain SQL (never through the repository under test).
 *
 * Each table's implementation adds one method (`organization()`, `user()`, …) taking named
 * overrides, filling the rest with valid defaults (unique values for unique columns) and
 * returning the new id. A fixture creates the rows it depends on.
 */
final class Fixtures
{
    /** A fixed `password_hash()`-shaped digest; fixtures never hash at run time. */
    public const PASSWORD_DIGEST = '$2y$10$abcdefghijklmnopqrstuuJ6C3D5yWv0cZx1vQ8kYb2e4f6g8h0i2';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * A value unique to this run, e.g. `user-3f9a1c0b2d4e`, for unique columns.
     */
    public function unique(string $prefix): string
    {
        return $prefix . '-' . bin2hex(random_bytes(6));
    }

    /**
     * Inserts one row and returns its `AUTO_INCREMENT` id.
     *
     * `$table` and the keys of `$values` are identifiers and must be literals from the fixture
     * source; the values are bound as named parameters.
     *
     * @param array<string, mixed> $values Column name => value.
     */
    public function insert(string $table, array $values): int
    {
        $columns = array_keys($values);
        $statement = $this->pdo->prepare(
            "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . '`) '
            . 'VALUES (:' . implode(', :', $columns) . ')',
        );
        $statement->execute($values);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Inserts an organization and returns its id.
     */
    public function organization(?string $name = null): int
    {
        return $this->insert('organizations', ['name' => $name ?? $this->unique('organization')]);
    }

    /**
     * Inserts a user and returns its id, creating an organization when none is given.
     *
     * Defaults: unique username and email, an approved, non-admin user.
     */
    public function user(
        ?int $organizationId = null,
        ?string $username = null,
        ?string $email = null,
        string $passwordDigest = self::PASSWORD_DIGEST,
        bool $isAdmin = false,
        string $status = 'approved',
    ): int {
        return $this->insert('users', [
            'organization_id' => $organizationId ?? $this->organization(),
            'username' => $username ?? $this->unique('user'),
            'email' => $email ?? $this->unique('user') . '@example.com',
            'password_digest' => $passwordDigest,
            'is_admin' => (int) $isAdmin,
            'status' => $status,
        ]);
    }

    /**
     * Inserts a refresh token and returns its id, creating a user when none is given.
     *
     * Defaults: a random digest and session uuid, issued now, expiring in 30 days, not
     * revoked, with the session started when the token was issued. Dates are UTC
     * `Y-m-d H:i:s` strings.
     */
    public function refreshToken(
        ?int $userId = null,
        ?string $tokenHash = null,
        ?string $sessionUuid = null,
        ?string $issuedAt = null,
        ?string $expiresAt = null,
        ?string $revokedAt = null,
        ?string $revokedReason = null,
        ?string $startedAt = null,
    ): int {
        $issuedAt ??= $this->at('now');

        return $this->insert('refresh_tokens', [
            'token_hash' => $tokenHash ?? $this->digest(),
            'user_id' => $userId ?? $this->user(),
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt ?? $this->at('+30 days'),
            'revoked_at' => $revokedAt,
            'revoked_reason' => $revokedReason,
            'session_uuid' => $sessionUuid ?? $this->uuid(),
            'started_at' => $startedAt ?? $issuedAt,
        ]);
    }

    /**
     * Inserts a password reset token and returns its id, creating a user when none is given.
     *
     * Defaults: a random digest, created now, expiring in one hour, unused.
     */
    public function passwordResetToken(
        ?int $userId = null,
        ?string $tokenHash = null,
        ?string $createdAt = null,
        ?string $expiresAt = null,
        ?string $usedAt = null,
    ): int {
        return $this->insert('password_reset_tokens', [
            'token_hash' => $tokenHash ?? $this->digest(),
            'user_id' => $userId ?? $this->user(),
            'created_at' => $createdAt ?? $this->at('now'),
            'expires_at' => $expiresAt ?? $this->at('+1 hour'),
            'used_at' => $usedAt,
        ]);
    }

    /**
     * Inserts a device authorization request and returns its id.
     *
     * `$userId` false (the default) creates a user; null stores a null `user_id` (a username
     * that does not exist). Defaults: a unique username, random uuid and poll token digest,
     * created now, expiring in 10 minutes, no approver and no attempts.
     */
    public function authorizationRequest(
        int|false|null $userId = false,
        ?string $uuid = null,
        ?string $pollTokenHash = null,
        string $status = 'open',
        ?string $username = null,
        ?int $approvedByUserId = null,
        ?string $createdAt = null,
        ?string $expiresAt = null,
    ): int {
        return $this->insert('authorization_requests', [
            'uuid' => $uuid ?? $this->uuid(),
            'username' => $username ?? $this->unique('user'),
            'user_id' => $userId === false ? $this->user() : $userId,
            'status' => $status,
            'poll_token_hash' => $pollTokenHash ?? $this->digest(),
            'request_ip' => '203.0.113.7',
            'request_user_agent' => 'PHPUnit',
            'approved_by_user_id' => $approvedByUserId,
            'created_at' => $createdAt ?? $this->at('now'),
            'expires_at' => $expiresAt ?? $this->at('+10 minutes'),
        ]);
    }

    /**
     * Inserts a user's account edit lockout row and returns its id, creating a user when none
     * is given.
     */
    public function accountEditLockout(
        ?int $userId = null,
        int $failedAttempts = 0,
        ?string $lockedUntil = null,
    ): int {
        return $this->insert('account_edit_lockouts', [
            'user_id' => $userId ?? $this->user(),
            'failed_attempts' => $failedAttempts,
            'locked_until' => $lockedUntil,
        ]);
    }

    /**
     * A random SHA-256-shaped hex digest (64 characters), never derived from a plain token.
     */
    private function digest(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * A random version 4 UUID string.
     */
    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * A `DATETIME` value in UTC for a `strtotime()` expression relative to now (e.g. `+1 hour`).
     */
    private function at(string $relative): string
    {
        return gmdate('Y-m-d H:i:s', (int) strtotime($relative));
    }
}
