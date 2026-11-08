# Implement Email

Purpose: index of the temporary implementation spec of epic #149 (SMTP connections, password
encryption, SMTP transport and test email), mapping the permanent specs to the implementation
work and collecting every open decision.

> Temporary — part of epic #149. Permanent specs win on conflict; update them, not this
> file, when a decision changes the target behavior.

## Scope

In scope (epic #149):

- the SMTP connection endpoints: list, create, read, update, delete, activate, deactivate;
- encryption at rest of the stored SMTP password;
- the SMTP transport and the synchronous send flow (connect, encryption, `AUTH`, `MAIL FROM` /
  `RCPT TO` / `DATA`, `QUIT`, failure mapping);
- the test email (`POST /smtp-connections/:id/test.json`);
- wiring the existing frontend SMTP screens (#43) to the real backend;
- the test infrastructure needed for all of the above (fake SMTP server, Mailpit).

Out of scope (later epics): `POST /v1/messages` and its token authentication, and the
password-recovery / registration emails. They reuse the send flow built here but are not
implemented by #149.

Already implemented and reused: the `smtp_connections` table, `Pidgey\Model\SmtpConnection`,
`Pidgey\Model\SmtpEncryption` and `Pidgey\Repository\SmtpConnectionRepository` (#89); the
cookie-session gate, origin check and body validation (#120); the frontend SMTP screens and
`SmtpConnectionsClient` (#43, never run against a real backend).

## Detail files

| File | Content | Owner issue |
| --- | --- | --- |
| [encryption.md](implement-email/encryption.md) | Password encryption at rest: service contract, key handling, rotation, failure on decrypt. | #151 (settled) |
| [transport.md](implement-email/transport.md) | SMTP transport and send flow: transport and stream contracts, result / failure values, `SMTP_*` mapping, timeouts, TLS, `AUTH`, new configuration keys. | #152 (settled) |
| [endpoints.md](implement-email/endpoints.md) | Connection endpoints (CRUD), activate, deactivate, test email handler (host policy, rate limit), operator log: hardening and lifecycle. | #153 (settled) |
| [testing.md](implement-email/testing.md) | Test infrastructure: fake SMTP server, Mailpit integration, fixtures. | #154 |
| [frontend.md](implement-email/frontend.md) | Frontend alignment of the SMTP screens with the real API. | #154 |

Each detail file starts with its **Open items** (every one marked **Pending decision**), then
describes its building blocks. Each open item appears in exactly one detail file.

## Building blocks

| Building block | Governing permanent specs | Detail file |
| --- | --- | --- |
| Password encryption | [email/smtp-connections.md](email/smtp-connections.md) (Security); [organizations/data-model/smtp-connections.md](organizations/data-model/smtp-connections.md) (`password` column); [architecture/configuration.md](architecture/configuration.md#secret-keys) | [encryption.md](implement-email/encryption.md) |
| SMTP transport | [email/send-flow.md](email/send-flow.md) (steps 5–7, timeouts); [email/failures.md](email/failures.md); [email/smtp-connections.md](email/smtp-connections.md) (`encryption`, credentials); [architecture/backend.md](architecture/backend.md#layers) (SMTP transport layer); [architecture/dependencies.md](architecture/dependencies.md) | [transport.md](implement-email/transport.md) |
| Send flow | [email/send-flow.md](email/send-flow.md); [email/message-scope.md](email/message-scope.md) (v1 fields, `From:`); [email/failures.md](email/failures.md); [api/conventions/errors.md](api/conventions/errors.md#smtp-send-failures) | [transport.md](implement-email/transport.md) |
| Connection endpoints (list, create, read, update, delete) | [api/endpoints/pidgey-admin/smtp-connections.md](api/endpoints/pidgey-admin/smtp-connections.md); [api/endpoints/pidgey-admin.md](api/endpoints/pidgey-admin.md) (access, guards, `:id`, shared rules); [organizations/scope.md](organizations/scope.md); [api/conventions/validation.md](api/conventions/validation.md) | [endpoints.md](implement-email/endpoints.md) |
| Activate and deactivate | [api/endpoints/pidgey-admin/smtp-connections.md](api/endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsidactivatejson); [email/smtp-connections.md](email/smtp-connections.md) (Active connection); [organizations/data-model/smtp-connections.md](organizations/data-model/smtp-connections.md#at-most-one-active-connection); [persistence/connection.md](persistence/connection.md) (transactions) | [endpoints.md](implement-email/endpoints.md) |
| Test email | [api/endpoints/pidgey-admin/smtp-connections.md](api/endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsidtestjson); [email/send-flow.md](email/send-flow.md) (steps 5–8); [email/failures.md](email/failures.md); [email/smtp-connections.md](email/smtp-connections.md#host-policy); [organizations/data-model/smtp-test-limits.md](organizations/data-model/smtp-test-limits.md) | [endpoints.md](implement-email/endpoints.md) |
| Operator log | [api/endpoints/pidgey-admin.md](api/endpoints/pidgey-admin.md#operator-log); [email/send-flow.md](email/send-flow.md) (step 8); [architecture/backend.md](architecture/backend.md#logging) (`LoggerFactory`); [architecture/configuration.md](architecture/configuration.md#logging) (`LOG_LEVEL`) | [endpoints.md](implement-email/endpoints.md) |
| Frontend wiring | [frontend/screens.md](frontend/screens.md#smtp-connections-screens); [frontend/layout.md](frontend/layout.md) (`SmtpConnectionsClient`, `ApiError`); [frontend/api-compatibility.md](frontend/api-compatibility.md) | [frontend.md](implement-email/frontend.md) |
| Test infrastructure | [email/testing.md](email/testing.md); [testing/backend.md](testing/backend.md); [docker/compose.md](docker/compose.md) (`mailpit-plain`, `mailpit-starttls`, `mailpit-ssl`, `tests`); [architecture/configuration.md](architecture/configuration.md#test-only-keys) (`TEST_MAILPIT_*`) | [testing.md](implement-email/testing.md) |

## Lifecycle rules

1. **#150** creates this index and its detail files with open items only.
2. **Spec issues (#151–#154)** each settle their own detail file. Decisions on *target
   behavior* go into the **permanent specs**; the detail file keeps only *implementation
   notes* (classes, order, test setup) linking to the permanent sections. A settled item is
   replaced by a link to the permanent section that now holds the decision.
3. **Implementation issues** (phase 3) read this spec, and each ticks off its items in the
   matching detail file **in the same PR** that implements them.
4. **Cleanup issue** (the last issue of #149): once every item is ticked, it moves anything
   still useful into the permanent docs (`architecture.md`, `testing/`, …), deletes
   `implement-email.md` and the `implement-email/` folder, and removes the hub row in
   [specs.md](../specs.md).
5. **Guard:** the cleanup issue's acceptance includes a grep proving that no file under
   `docs/agents/` still links to `implement-email`.

Names proposed in the detail files are **proposals**: `plan-issue` may rename them, leaving a
note in the detail file.

## Implementation sequence sketch

Non-binding: titles and dependencies only. The real implementation issues are created later,
after #151–#154 land, by re-running `/arcanum-split-issue 149`.

| # | Title | Depends on |
| --- | --- | --- |
| 1 | Password encryption service | #151 |
| 2 | SMTP connection endpoints (list, create, read, update, delete), activate and deactivate | 1, #153 |
| 3 | Fake SMTP server and Mailpit test support | #154 |
| 4 | SMTP transport and send flow | #152, 3 |
| 5 | Test email endpoint | 1, 2, 4 |
| 6 | Frontend wiring against the real API | 2, 5, #154 |
| 7 | Cleanup: move what is useful into permanent docs, delete `implement-email*` | every other issue |

Test infrastructure (3) is built alongside the transport (4); the operator log is delivered
with the endpoints (2) and the test email (5).

## Unassigned

None.
