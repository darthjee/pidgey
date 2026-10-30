# Plan: Persistence: auth token tables, entities and repositories

Issue: [87-persistence-auth-token-tables-entities-and-repositories.md](../../issues/87-persistence-auth-token-tables-entities-and-repositories.md)

## Overview

Add the four authentication tables (`refresh_tokens`, `password_reset_tokens`,
`authorization_requests`, `account_edit_lockouts`), each with an entity, a repository and a
`Container` getter, plus two enums, fixtures and `SchemaTest` expectations. The work follows
the persistence specs and the patterns #86 set for `organizations` and `users`. First, the
product owner records in the persistence spec that timestamps compared in PHP are passed
explicitly to `create()`. Backend then implements against that spec.

## Agents involved

- [product-owner](product-owner.md)
- [backend](backend.md)

## Shared contracts

- **Spec rule, added to the *Time* section of `docs/agents/specs/persistence/repositories.md`:**
  - A creation timestamp that the application later compares in PHP is a
    `DateTimeImmutable` argument of `create()`, read by the caller from `WallClock`.
    This covers `refresh_tokens.issued_at`, `password_reset_tokens.created_at` and
    `authorization_requests.created_at`.
  - The column keeps `DEFAULT CURRENT_TIMESTAMP` in the schema.
  - Other `created_at`/`updated_at` columns keep relying on the DB default.
- **Repository signatures** (backend implements exactly these; the spec may cite them):
  - `RefreshTokenRepository::create(string $tokenHash, int $userId, string $sessionUuid, DateTimeImmutable $issuedAt, DateTimeImmutable $expiresAt, DateTimeImmutable $startedAt): RefreshToken`
  - `PasswordResetTokenRepository::create(string $tokenHash, int $userId, DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt): PasswordResetToken`
  - `AuthorizationRequestRepository::create(string $uuid, string $username, ?int $userId, string $pollTokenHash, string $requestIp, string $requestUserAgent, DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt): AuthorizationRequest`
  - `AccountEditLockoutRepository::create(int $userId): AccountEditLockout`
