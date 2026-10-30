# Backend plan: Persistence: SMTP connections table, entity and repository

Main plan: [plan.md](plan.md)

## Overview
Implements the last table of epic #84, `smtp_connections`. The work mirrors #88 (`api_tokens`):
- one migration;
- one enum and one entity;
- one organization-scoped repository;
- a `Container` getter and a `Fixtures` method;
- `SchemaTest` expectations, plus unit and integration tests.

## Context
- Table spec: `docs/agents/specs/organizations/data-model/smtp-connections.md`.
- Field and security rules: `docs/agents/specs/email/smtp-connections.md`.
- Persistence rules: `docs/agents/specs/persistence/` (`schema-migrations.md`, `entities.md`,
  `repositories.md`, `testing.md`).
- Existing patterns to copy:
  - `source/migrations/0007_create_api_tokens.sql`;
  - `source/lib/Model/ApiToken.php` and `source/lib/Model/UserStatus.php`;
  - `source/lib/Repository/ApiTokenRepository.php`;
  - `Fixtures::apiToken()`;
  - the `api_tokens` tests in `SchemaTest`, `ApiTokenTest`, `ApiTokenRepositoryTest` and
    `ContainerTest`.
- The connection uses native prepares (`PDO::ATTR_EMULATE_PREPARES => false`) and `FETCH_ASSOC`.

## Steps

- [01 — Migration and schema test](backend/01-migration-and-schema-test.md)
- [02 — Enum and entity](backend/02-enum-and-entity.md)
- [03 — Repository, container, fixture and tests](backend/03-repository-container-fixture-and-tests.md)

## CI Checks
- `source`: `composer lint` (CI job: lint)
- `source`: `composer db:prepare && composer coverage` (CI job: tests)
- `source`: `composer db:status`: no pending or edited migration after `db:prepare`.

## Notes
- Migration number: `0008` is the next free number, since #87 (`0003`–`0006`) and #88
  (`0007`) are merged. Merged migrations are never edited.
- Business rules stay out of the repository:
  - "`username` and `password` both set or both empty";
  - which connection is active;
  - password encryption.

  Do not validate them in `create()`.
- `create()` has no `active` argument and does not list `active` in its `INSERT`: the column
  default (`FALSE`) makes new rows inactive, as the spec requires.
- Security: the `password` column holds ciphertext as opaque bytes. Never log bound values,
  and never add the password to an exception message. The `security` agent reviews the change
  before merge.
- Out of scope:
  - activate, deactivate, update and delete methods;
  - `lock…` methods;
  - the database-level "one active connection" constraint;
  - password encryption;
  - SMTP sending.
