# Plan: Specs: email integration implementation spec

Issue: [150-specs-email-integration-implementation-spec.md](../../issues/150-specs-email-integration-implementation-spec.md)

## Overview

Write the temporary implementation spec of epic #149: an index
(`docs/agents/specs/implement-email.md`) and five detail files under
`docs/agents/specs/implement-email/` — `encryption.md` (#151), `transport.md` (#152),
`endpoints.md` (#153), `testing.md` (#154), `frontend.md` (#154) — plus one hub row in
`docs/agents/specs.md`.

## Context

- Permanent specs that govern the work: `email/smtp-connections.md`, `email/send-flow.md`,
  `email/failures.md`, `email/message-scope.md`, `email/testing.md`,
  `api/endpoints/pidgey-admin.md`, `api/endpoints/pidgey-admin/smtp-connections.md`,
  `api/pending.md`, `organizations/data-model/smtp-connections.md`, `organizations/scope.md`,
  `architecture/configuration.md`, `testing/backend.md`, `docker/compose.md`,
  `frontend/screens.md#smtp-connections-screens`.
- Existing code to reference as collaborators: `Pidgey\Model\SmtpConnection`,
  `Pidgey\Model\SmtpEncryption`, `Pidgey\Repository\SmtpConnectionRepository`,
  `Pidgey\Auth\Gate`, `Pidgey\Auth\SecretKeys`, `Pidgey\Http\Validator`,
  `Pidgey\Http\FieldRules`, `Pidgey\Http\OriginCheck`, `Pidgey\Http\Router` /
  `source/config/routes/*.php`, `Pidgey\Http\Handler\*`, `Pidgey\Infra\Transaction`,
  `Pidgey\Infra\Clock`, `Pidgey\Infra\ConfigValidator`, `Pidgey\Container`; frontend
  `frontend/assets/js/components/resources/smtpConnections/` (#43).
- Rules every file follows (from the issue):
  - title, one-line purpose, then the temporary marker:
    `> Temporary — part of epic #149. Permanent specs win on conflict; update them, not this
    file, when a decision changes the target behavior.`
  - per building block: **Responsibility**, **Proposed names** (proposals; `plan-issue` may
    rename with a note), **Collaborators**, **Governing specs**, **Open items**;
  - shared contracts described one level deeper (what each carries: fields and meaning), no
    PHP signatures;
  - excluded: method signatures, SQL, algorithms, per-test case lists, file-by-file changes;
  - open items written as `**Pending decision:**`; working assumptions written as
    `*Working assumption, to be confirmed in #15x*`; inconsistencies found in permanent specs
    listed as open items, not fixed.
- Each open item appears in exactly one detail file.

## Steps

- [01 — Index and hub row](product-owner/01-index-and-hub-row.md)
- [02 — Encryption detail file](product-owner/02-encryption.md)
- [03 — Transport detail file](product-owner/03-transport.md)
- [04 — Endpoints detail file](product-owner/04-endpoints.md)
- [05 — Testing and frontend detail files](product-owner/05-testing-and-frontend.md)

## Notes

- No tests or linters check spec files; verification is PR review against the issue's
  acceptance list.
- Spell the folder `implement-email` (not `implemnt-email`).
- If an open item fits none of #151–#154, put it under **Unassigned** in the index; do not
  create an issue.
