# Plan: Persistence: SMTP connections table, entity and repository

Issue: [89-persistence-smtp-connections-table-entity-and-repository.md](../../issues/89-persistence-smtp-connections-table-entity-and-repository.md)

## Overview
Add migration `0008_create_smtp_connections.sql`, the `SmtpEncryption` enum, the `SmtpConnection`
entity and the `SmtpConnectionRepository` (`create`, `findInOrganization`, `listByOrganization`),
with the container getter, a fixture, schema expectations and tests. All the work is in `source/`.

See [backend.md](backend.md) for the full plan.
