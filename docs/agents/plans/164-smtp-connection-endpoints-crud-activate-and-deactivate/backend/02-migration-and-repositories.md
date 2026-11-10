# Migration and repositories

- Migration `0010_add_smtp_connections_active_unique.sql`: `ALTER TABLE smtp_connections ADD
  COLUMN active_organization_id BIGINT GENERATED ALWAYS AS (IF(active, organization_id, NULL))
  STORED`, `ADD UNIQUE INDEX uq_smtp_connections_active_organization_id
  (active_organization_id)`. The column is never selected (`COLUMNS` unchanged) nor written.
- `OrganizationRepository::lockForUpdate(int $id): bool` (`SELECT id … FOR UPDATE`).
- `SmtpConnectionRepository`:
  - `lockInOrganization(orgId, id): ?SmtpConnection` (`… FOR UPDATE`, full columns);
  - `update(SmtpConnection $merged, bool $passwordChanged, ?string $encryptedPassword)` —
    writes name, host, port, encryption, username, from_address and, only when changed,
    password; **never** `active`;
  - `setPassword(orgId, id, string $encrypted)` for the create ordering (or reuse
    `replaceEncryptedPassword` if its contract fits);
  - `delete(orgId, id): bool`;
  - `activate(orgId, id)`: `UPDATE … SET active = FALSE WHERE organization_id = ? AND id <> ?`
    then `SET active = TRUE WHERE id = ?`; `deactivate(orgId, id)`: `SET active = FALSE` on
    the target only. Both must run inside a transaction that already holds the organization
    lock (documented, not checked).
- Integration tests (repository suite): each method; organization scoping; the unique index
  rejects a second active row of the same organization and allows several inactive ones and
  one active per organization; `update` never touches `active`.

## Files to Change
- `source/migrations/0010_add_smtp_connections_active_unique.sql` — new.
- `source/lib/Repository/OrganizationRepository.php` — `lockForUpdate`.
- `source/lib/Repository/SmtpConnectionRepository.php` — new methods.
- `source/tests/integration/Repository/SmtpConnectionRepositoryTest.php`,
  `OrganizationRepositoryTest.php`, `source/tests/integration/Schema/*` — tests.
