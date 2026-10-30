# Backend Plan: Persistence: API tokens table, entity and repository

Main plan: [plan.md](plan.md)

## Overview

Persist organization API tokens: the table, its entity and its repository. Only the SHA-256
digest of a token and its non-secret display prefix are stored. Issuing tokens, Bearer
authentication and the endpoints are out of scope.

## Context

- Table spec: `docs/agents/specs/organizations/data-model/api-tokens.md`.
- Persistence specs: `docs/agents/specs/persistence/` (`schema-migrations.md`,
  `entities.md`, `repositories.md`, `testing.md`).
- Token rules: `docs/agents/specs/api/authentication/api-tokens.md`.
- Patterns to copy:
  - `source/migrations/0002_create_users.sql`, the organization-scoped table;
  - `source/lib/Repository/UserRepository.php`, for `findInOrganization`;
  - `source/lib/Repository/PasswordResetTokenRepository.php`, for `findByTokenHash`;
  - `source/lib/Model/User.php`;
  - `source/tests/support/Fixtures.php`;
  - `source/tests/integration/Schema/SchemaTest.php`.
- #87 is merged and holds migrations `0003`–`0006`, so this issue takes `0007`.

## Steps

- [01 — Migration and schema test](backend/01-migration-and-schema-test.md)
- [02 — Entity](backend/02-entity.md)
- [03 — Repository, container, fixture and tests](backend/03-repository-container-fixture-and-tests.md)

## CI Checks

- `source`: `composer lint` (CI job: lint).
- `source`: `composer db:prepare && composer coverage` (CI job: tests with coverage).
  Coverage must stay at 100% for new code.
- After migrating, `composer db:status` shows no pending or edited file.

## Notes

- **Security review is required before merge.** It checks that only the digest and the
  prefix are stored, that all SQL uses named placeholders, and that the unscoped
  `findByTokenHash` carries a doc comment justifying it.
- Renumber the migration only if #89 merges first and takes `0007`.
- Deferred to the endpoint issues:
  - `listByOrganization` (ordered by `id`), `revoke` and `touchLastUsed`;
  - `ApiToken::isRevoked()`.
