# Plan: Persistence: API tokens table, entity and repository

Issue: [88-persistence-api-tokens-table-entity-and-repository.md](../../issues/88-persistence-api-tokens-table-entity-and-repository.md)

## Overview

Add the `api_tokens` table (migration `0007`), the `ApiToken` entity and the
`ApiTokenRepository` (`create`, `findInOrganization`, `findByTokenHash`), with the container
getter, `Fixtures::apiToken()`, `SchemaTest` expectations, and unit and integration tests. The
work follows the patterns of #86 and #87.

See [backend.md](backend.md) for the full plan.
