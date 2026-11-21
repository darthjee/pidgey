# Migration, model and repository

Create the `idempotency_keys` table and its data access, following the patterns of `smtp_test_limits` (`0011_create_smtp_test_limits.sql`, `Model/SmtpTestLimit.php`, `Repository/SmtpTestLimitRepository.php`).

- Migration `0012_create_idempotency_keys.sql` with the exact columns and keys of the shared contract (`ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci`).
- `Model/IdempotencyKey.php`: a readonly row (`fromRow`) with `state`, `requestHash`, `responseStatus`, `responseBody`, `leaseUntil`, `expiresAt`.
- `Repository/IdempotencyKeyRepository.php`:
  - `find(int $organizationId, string $keyHash): ?IdempotencyKey`;
  - `reserve(...)`: inserts an `in_flight` row; overwrites an expired row for the same key; returns `false` on a duplicate-key error (row exists and is not expired) instead of throwing;
  - `complete(int $organizationId, string $keyHash, int $status, string $body): void`;
  - `release(int $organizationId, string $keyHash): void` (delete);
  - `deleteExpired(int $organizationId, DateTimeImmutable $now, int $limit): int` (`DELETE … WHERE organization_id = ? AND expires_at <= ? LIMIT ?`).
- Register the repository in `Container.php`.
- Integration tests: repository behavior (reserve, duplicate, overwrite expired, complete, release, bounded delete scoped to the organization) and the schema tests (tables, columns, keys).

## Files to Change

- `source/migrations/0012_create_idempotency_keys.sql` — new table.
- `source/lib/Model/IdempotencyKey.php` — new row model.
- `source/lib/Repository/IdempotencyKeyRepository.php` — new repository.
- `source/lib/Container.php` — wire the repository.
- `source/tests/integration/Repository/IdempotencyKeyRepositoryTest.php` — new.
- `source/tests/integration/Schema/SchemaTablesTest.php`, `SchemaColumnsTest.php`, `SchemaKeysTest.php` — add the table.
- `source/tests/unit/Model/` — model test if the other models have one.
