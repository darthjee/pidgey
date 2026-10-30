# Issue: Persistence: SMTP connections table, entity and repository

## Description

Part of epic #84; depends on #86 (`organizations`). Create the `smtp_connections` table with its entity and repository, following the persistence specs ([schema-migrations.md](docs/agents/specs/persistence/schema-migrations.md), [entities.md](docs/agents/specs/persistence/entities.md), [repositories.md](docs/agents/specs/persistence/repositories.md), [testing.md](docs/agents/specs/persistence/testing.md)) and the table spec [organizations/data-model/smtp-connections.md](docs/agents/specs/organizations/data-model/smtp-connections.md). #87 and #88 are merged, so `0008` is the next free migration number.

## Problem

The admin SMTP-connection endpoints and the sending path need somewhere to store each organization's SMTP settings. No table, entity or repository exists yet.

## Expected Behavior

- Migration `source/migrations/0008_create_smtp_connections.sql` creates `smtp_connections` exactly as the table spec defines it:
  - `password` is `VARBINARY(1024) NULL`;
  - `active` is `BOOLEAN NOT NULL DEFAULT FALSE`;
  - `created_at` / `updated_at` use the usual defaults;
  - plain index `ix_smtp_connections_organization_id`;
  - foreign key `fk_smtp_connections_organization_id` → `organizations (id) ON DELETE RESTRICT`;
  - no unique keys;
  - `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci`.
- Enum `Pidgey\Model\SmtpEncryption` (string-backed: `None` = `none`, `Tls` = `tls`, `Ssl` = `ssl`).
- Entity `Pidgey\Model\SmtpConnection` (`final readonly class`, `fromRow()`) with:
  - `id`, `organizationId`, `name`, `host`, `port`, `encryption` (`SmtpEncryption`), `username` (`?string`), `encryptedPassword` (`?string`, the raw `password` column bytes), `fromAddress`, `active` (`bool`), `createdAt`, `updatedAt`;
  - `hasPassword(): bool`, true when `encryptedPassword` is not `null`.
- `Pidgey\Repository\SmtpConnectionRepository`:
  - `create(int $organizationId, string $name, string $host, int $port, SmtpEncryption $encryption, ?string $username, ?string $encryptedPassword, string $fromAddress): SmtpConnection` takes no `active` argument: the row starts inactive through the column default. It re-reads the row through `findInOrganization` so defaults are reflected. The password is stored as opaque bytes, bound as binary (`PDO::PARAM_LOB`).
  - `findInOrganization(int $organizationId, int $id): ?SmtpConnection`.
  - `listByOrganization(int $organizationId): list<SmtpConnection>`, ordered by `id` ascending, empty list when none.
- `Container::smtpConnectionRepository()`: a typed, memoized getter built on `Container::pdo()`.
- `Fixtures::smtpConnection(...)`:
  - takes named overrides and creates an organization when none is given;
  - inserts with plain SQL;
  - defaults to an inactive connection with no credentials.
- `SchemaTest` covers the new table: columns, types, nullability, defaults, the index, the foreign key, and collation.

## Solution

**Tests**
- Unit (`source/tests/unit/Model/`):
  - `SmtpConnection::fromRow()` converts every column, including `null` username and password, the enum, `active` as `bool`, and UTC dates;
  - a missing column throws, and so does an unknown `encryption` value;
  - `hasPassword()` is checked both ways.
- Integration (`source/tests/integration/Repository/`), each test inside a rolled-back transaction with no literal ids:
  - `create` with credentials and without;
  - the new row is inactive;
  - password bytes round-trip unchanged (non-UTF-8 and NUL bytes included);
  - `create` with an unknown organization throws `PDOException`;
  - `findInOrganization` finds the row, returns `null` for an unknown id, and returns `null` for another organization's row;
  - `listByOrganization` orders by id, excludes other organizations, and returns an empty list when there are none.
- `ContainerTest` covers the new getter.

**Kept out of the repository** (services or handlers own these, per repositories.md):
- the "`username` and `password` both set or both empty" rule;
- deciding which connection is active;
- encrypting the password.

**Security**
- The repository only reads and writes ciphertext bytes. The encryption mechanism stays a pending decision in [email/smtp-connections.md](docs/agents/specs/email/smtp-connections.md); it blocks the service that encrypts, not this table.
- The entity never holds a plain-text password, and nothing here serializes it.
- The `security` agent reviews the change before merge.

**Out of scope**
- Password encryption.
- Activate, deactivate, update and delete methods; endpoint issues add them when they need them.
- A `lock…` method.
- The database-level "at most one active connection" constraint (a later migration if adopted).
- SMTP sending.

## Benefits

Completes the persistence layer of epic #84. The SMTP-connection admin endpoints and message sending can then build on a tested repository.
