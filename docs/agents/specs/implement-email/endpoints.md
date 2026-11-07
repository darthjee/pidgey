# Implement Email: Connection Endpoints

Purpose: map the SMTP connection endpoints (CRUD, activate, test email) and their operator
log to the implementation work and list the hardening and lifecycle decisions owned by #153.

> Temporary — part of epic #149. Permanent specs win on conflict; update them, not this
> file, when a decision changes the target behavior.

Index: [implement-email.md](../implement-email.md).

## Open items

- [ ] **Pending decision:** SMTP host deny-list / allow-list against server-side request
  forgery ([api/pending.md](../api/pending.md),
  [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#known-limitations)):
  whether it exists, its configuration, whether it is checked on create / update, at send
  time, or both, and the error answered.
- [ ] **Pending decision:** DNS rebinding. A host check at validation time can be bypassed by
  a name that resolves to an allowed address first and an internal one at send time; whether
  the send flow resolves the name once, checks the resolved addresses and connects to the
  checked address (keeping `host` for TLS peer verification).
- [ ] **Pending decision:** credential redirection. Require `password` to be re-sent (or
  cleared) in the same `PATCH` when `host`, `port` or `encryption` changes on a connection
  that has credentials ([api/pending.md](../api/pending.md)); the validation message if
  adopted.
- [ ] **Pending decision:** test-email rate limit ([api/pending.md](../api/pending.md)): scope
  (per user, per organization, per connection, per IP), window and count, storage, and the
  `429` answer. It also covers **worker exhaustion** (slow SMTP servers holding PHP workers up
  to the send-flow timeouts, possibly a cap on concurrent test emails) and **arbitrary `to`**
  (the test email can send to any address, a spam / reputation vector).
- [ ] **Pending decision:** a `deactivate` endpoint
  (`POST /smtp-connections/:id/deactivate.json`).
- [ ] **Pending decision:** a database-level "at most one active connection per organization"
  constraint (v1 relies on the locked transaction of activate; see also
  [persistence/schema-migrations.md](../persistence/schema-migrations.md)).
- [ ] **Pending decision (inconsistency):**
  [organizations/data-model/smtp-connections.md](../organizations/data-model/smtp-connections.md)
  states "Pending decisions: None" while the database-level constraint above is pending in
  [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#pending-decisions)
  and [email/smtp-connections.md](../email/smtp-connections.md); and the pending summary of
  [api/endpoints/pidgey-admin.md](../api/endpoints/pidgey-admin.md#pending-decisions) omits
  the credential-redirection, rate-limit and message-template items.
- [ ] **Pending decision:** a connection edited or deleted while its test email is in flight:
  whether the test uses a snapshot of the row read at the start (and the result still
  reports it), and what the operator log line says when the row no longer exists.
- [ ] **Pending decision:** `PATCH` racing `activate` on the same connection (or on another
  connection of the organization): whether `PATCH` also takes the organization lock, and
  whether a `PATCH` may be lost or may overwrite `active`.
- [ ] **Pending decision:** adopting the messages for numeric ranges, hostnames and control
  characters as shared templates in
  [api/conventions/validation.md](../api/conventions/validation.md#message-templates) (pending
  in [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#pending-decisions)).
- [ ] **Pending decision:** operator log destination and format. The code has no monolog
  logger wired yet (existing handlers log through `error_log()`); whether a logger factory in
  `Pidgey\Infra\` is introduced here, the line format (structured context or text), and the
  log level (the log-level variable is pending in
  [architecture/configuration.md](../architecture/configuration.md#pending-decisions)).

## Building block: connection endpoints

- **Responsibility:** `GET` / `POST /smtp-connections.json` and `GET` / `PATCH` / `DELETE
  /smtp-connections/:id.json`: validate the body with the field rules, enforce credential
  pairing on the merged state, encrypt a new password, persist, and answer the SMTP connection
  item. They never return the password and never change `active`.
- **Proposed names:** `Pidgey\Http\Handler\SmtpConnectionsHandler` (list, create) and
  `Pidgey\Http\Handler\SmtpConnectionHandler` (read, update, delete), a field-rule set
  `Pidgey\Http\SmtpConnectionRules`, a serializer `Pidgey\Http\SmtpConnectionPayload`, and
  routes in a new `source/config/routes/smtp-connections.php`. Proposals only.
- **Collaborators:** `Pidgey\Auth\Gate`, `Pidgey\Http\OriginCheck`, `Pidgey\Http\Validator`,
  `Pidgey\Http\FieldRules`, `Pidgey\Http\Router` and `source/config/routes/*.php`,
  `Pidgey\Http\HandlerFactory`, `Pidgey\Repository\SmtpConnectionRepository` (today: create,
  find in organization, list; update and delete to be added), the password encryption service
  ([encryption.md](encryption.md)), the operator log (below), `Pidgey\Infra\Clock`.
- **Governing specs:** [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md);
  [api/endpoints/pidgey-admin.md](../api/endpoints/pidgey-admin.md) (access, guards, `:id`,
  shared rules); [organizations/scope.md](../organizations/scope.md);
  [api/conventions/validation.md](../api/conventions/validation.md).
- **Open items:** host deny-list, credential redirection, message templates, the
  inconsistency item.

### Shared contract: SMTP connection item

The item returned by every endpoint is defined in
[api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#smtp-connection-item)
and is not copied here. It carries `id`, `name`, `host`, `port`, `encryption`, `username`,
`hasPassword`, `fromAddress`, `active`, `createdAt`, `updatedAt`; never `password` or
`organizationId`. The frontend `SmtpConnectionsClient` reads the same shape
([frontend.md](frontend.md)).

## Building block: activate

- **Responsibility:** `POST /smtp-connections/:id/activate.json`: in one transaction, lock the
  organization row, load the target, deactivate every other connection of the organization
  and activate the target. Idempotent.
- **Proposed names:** `Pidgey\Http\Handler\SmtpConnectionActivateHandler`, with the
  transactional step in the repository (e.g. `SmtpConnectionRepository::activate`) or a small
  `Pidgey\Email\ConnectionActivation`. Proposals only.
- **Collaborators:** `Pidgey\Infra\Transaction`, `Pidgey\Repository\OrganizationRepository`
  (row lock), `Pidgey\Repository\SmtpConnectionRepository`, the operator log.
- **Governing specs:** [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsidactivatejson);
  [email/smtp-connections.md](../email/smtp-connections.md) (Active connection);
  [persistence/connection.md](../persistence/connection.md).
- **Open items:** `deactivate`, database-level constraint, `PATCH` racing `activate`.

## Building block: test email

- **Responsibility:** `POST /smtp-connections/:id/test.json`: validate `to` (default: the
  caller's own email), load the given connection (active or not), run the send flow
  ([transport.md](transport.md#building-block-send-flow)) with the fixed test content, and
  answer `201` or the `SMTP_*` error. It never changes any data.
- **Proposed names:** `Pidgey\Http\Handler\SmtpConnectionTestHandler`, the fixed content in
  `Pidgey\Email\TestEmail`. Proposals only.
- **Collaborators:** the send flow, `Pidgey\Repository\SmtpConnectionRepository`,
  `Pidgey\Repository\UserRepository` (the caller's email), `Pidgey\Http\Validator`, the
  operator log.
- **Governing specs:** [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsidtestjson);
  [email/send-flow.md](../email/send-flow.md) (steps 5–8);
  [email/failures.md](../email/failures.md); [api/pending.md](../api/pending.md).
- **Open items:** rate limit (worker exhaustion, arbitrary `to`), DNS rebinding, edited or
  deleted during the test.

## Building block: operator log

- **Responsibility:** one line per successful state change (`smtp-connection-create`,
  `-update`, `-delete`, `-activate`) and one per test attempt that reached the send
  (`smtp-connection-test` with the outcome), holding the user id, organization id, action,
  connection id and, for updates, the changed field **names** only. Never passwords, never
  the recipient beyond what the send flow logs.
- **Proposed names:** `Pidgey\Infra\LoggerFactory` (monolog) and a small
  `Pidgey\Http\OperatorLog` writing the lines. Proposals only.
- **Collaborators:** `monolog/monolog`, `Pidgey\Container`, the handlers above.
- **Governing specs:** [api/endpoints/pidgey-admin.md](../api/endpoints/pidgey-admin.md#operator-log);
  [email/send-flow.md](../email/send-flow.md) (step 8);
  [architecture/backend.md](../architecture/backend.md#principles).
- **Open items:** operator log destination and format.
