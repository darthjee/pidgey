# Implement Email: Connection Endpoints

Purpose: map the SMTP connection endpoints (CRUD, activate, deactivate, test email) and their
operator log to the implementation work; the hardening and lifecycle decisions owned by #153
are settled in the permanent specs linked below.

> Temporary — part of epic #149. Permanent specs win on conflict; update them, not this
> file, when a decision changes the target behavior.

Index: [implement-email.md](../implement-email.md).

## Open items

All settled by #153.

- [x] **SMTP host deny-list (SSRF):** checked **at send time only** (test email and
  `POST /v1/messages`); `SMTP_ALLOW_PRIVATE_HOSTS` (default `false`, `true` in development
  and test) skips the denied ranges; a denied address answers
  `409 SMTP_HOST_NOT_ALLOWED`; ports are not restricted, the reachability oracle on public
  hosts stays a known limitation —
  [email/send-flow.md](../email/send-flow.md#host-check),
  [email/smtp-connections.md](../email/smtp-connections.md#host-policy),
  [email/failures.md](../email/failures.md#fixed-messages),
  [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#known-limitations).
- [x] **DNS rebinding:** `host` is resolved once, every address is checked, and the
  transport connects to the checked IP (IPv4 preferred, no fallback in v1), with `host` kept
  as TLS peer name and SNI — [email/send-flow.md](../email/send-flow.md#host-check).
- [x] **Credential redirection:** a `PATCH` changing `host`, `port` or `encryption` on a
  connection that keeps credentials must send a non-empty `password` or clear both;
  otherwise `400` — `password must be sent again when host, port or encryption changes` —
  [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#field-rules).
- [x] **Test-email rate limit:** per organization, fixed window (`SMTP_TEST_MAX_ATTEMPTS` /
  `SMTP_TEST_WINDOW`), one test in flight (lease), `429 TOO_MANY_REQUESTS` with
  `Retry-After`; `to` restricted to the organization's users —
  [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#test-email-limit),
  [organizations/data-model/smtp-test-limits.md](../organizations/data-model/smtp-test-limits.md),
  [api/conventions/errors.md](../api/conventions/errors.md#too-many-requests).
- [x] **`deactivate` endpoint:** `POST /smtp-connections/:id/deactivate.json`, organization
  lock, idempotent, `200` with the item —
  [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsiddeactivatejson).
- [x] **Database-level "at most one active":** generated stored column
  `active_organization_id` with a unique index (migration `0010`), defense in depth —
  [organizations/data-model/smtp-connections.md](../organizations/data-model/smtp-connections.md#at-most-one-active-connection).
- [x] **Spec inconsistency:** the data-model spec now holds the constraint and states no
  pending decision; the pending summary of
  [api/endpoints/pidgey-admin.md](../api/endpoints/pidgey-admin.md#pending-decisions) lists
  what remains.
- [x] **Edited or deleted during the test email:** the test uses the snapshot read at its
  start; the outcome is answered and logged with the connection id, without re-checking the
  row — [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsidtestjson).
- [x] **`PATCH` racing `activate`:** `PATCH` locks the connection row
  (`SELECT … FOR UPDATE`), does not take the organization lock, never writes `active` —
  [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#patch-smtp-connectionsidjson).
- [x] **Shared message templates:** integer, range, hostname, control-character and
  line-break templates — [api/conventions/validation.md](../api/conventions/validation.md#message-templates).
- [x] **Operator log destination and format:** `Pidgey\Infra\LoggerFactory` (Monolog,
  `ErrorLogHandler`), `info`, message = action name, JSON context `userId`,
  `organizationId`, `connectionId`, `fields`; `LOG_LEVEL` (default `info`) —
  [api/endpoints/pidgey-admin.md](../api/endpoints/pidgey-admin.md#operator-log),
  [architecture/backend.md](../architecture/backend.md#logging),
  [architecture/configuration.md](../architecture/configuration.md#logging).

## Building block: connection endpoints

- **Responsibility:** `GET` / `POST /smtp-connections.json` and `GET` / `PATCH` / `DELETE
  /smtp-connections/:id.json`: validate the body with the field rules (shared message
  templates), enforce credential pairing, credentials-require-encryption and the
  credential-redirection rule on the merged state, encrypt a new password, persist, and
  answer the SMTP connection item. `PATCH` locks the connection row for
  read-merge-validate-write. They never return the password and never change `active`.
- **Names (decided, #164):** `Pidgey\Http\Handler\SmtpConnectionsHandler` (list, create)
  and `Pidgey\Http\Handler\SmtpConnectionHandler` (read, update, delete); the field-rule
  set and fixed cross-field messages in `Pidgey\Http\SmtpConnectionRules`; the serializer
  `Pidgey\Http\SmtpConnectionPayload`; the `:id` parser `Pidgey\Http\PathId::parse()`
  (malformed → `400 BAD_REQUEST`, above `PHP_INT_MAX` → `404`); the host check of the
  validator in `Pidgey\Http\HostName::isValid()`. Routes live in
  `source/config/routes/organization.php`
  ([request-handler/routing.md](../request-handler/routing.md#route-files)), required from
  `source/config/routes.php` after `auth.php` and before the frontend fallback; every route
  lists `[Gate::class]` and the global `OriginCheck` covers the writes. The
  `smtp-connections.php` route file proposed earlier is dropped.
- **Collaborators:** `Pidgey\Auth\Gate`, `Pidgey\Http\OriginCheck`, `Pidgey\Http\Validator`,
  `Pidgey\Http\FieldRules`, `Pidgey\Http\Router` and `source/config/routes/*.php`,
  `Pidgey\Http\HandlerFactory`, `Pidgey\Repository\SmtpConnectionRepository` (today: create,
  find in organization, list; update and delete to be added), the password encryption service
  ([encryption.md](encryption.md)), the operator log (below), `Pidgey\Infra\WallClock`.
- **Governing specs:** [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md);
  [api/endpoints/pidgey-admin.md](../api/endpoints/pidgey-admin.md) (access, guards, `:id`,
  shared rules); [organizations/scope.md](../organizations/scope.md);
  [api/conventions/validation.md](../api/conventions/validation.md).
- **Settled:** credential redirection, message templates, `PATCH` row lock (see
  [Open items](#open-items)).

### Shared contract: SMTP connection item

The item returned by every endpoint is defined in
[api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#smtp-connection-item)
and is not copied here. It carries `id`, `name`, `host`, `port`, `encryption`, `username`,
`hasPassword`, `fromAddress`, `active`, `createdAt`, `updatedAt`; never `password` or
`organizationId`. The frontend `SmtpConnectionsClient` reads the same shape
([frontend.md](frontend.md)).

## Building block: activate and deactivate

- **Responsibility:** `POST /smtp-connections/:id/activate.json`: in one transaction, lock the
  organization row, load the target, deactivate every other connection of the organization
  and activate the target. `POST /smtp-connections/:id/deactivate.json`: in one transaction,
  lock the organization row, load the target, set `active = false`. Both idempotent, both
  answer `200` with the item. Migration `0010_add_smtp_connections_active_unique.sql` adds
  the generated `active_organization_id` column and its unique index (defense in depth).
- **Names (decided, #164):** `Pidgey\Http\Handler\SmtpConnectionActivateHandler` and
  `Pidgey\Http\Handler\SmtpConnectionDeactivateHandler`. The steps live in the
  repositories (`OrganizationRepository::lockForUpdate`,
  `SmtpConnectionRepository::activate` / `::deactivate`) and the handler wraps them in
  `Transaction::run`; there is **no** `Pidgey\Email\ConnectionActivation`.
- **Collaborators:** `Pidgey\Infra\Transaction`, `Pidgey\Repository\OrganizationRepository`
  (row lock), `Pidgey\Repository\SmtpConnectionRepository`, the operator log.
- **Governing specs:** [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsidactivatejson)
  and [deactivate](../api/endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsiddeactivatejson);
  [email/smtp-connections.md](../email/smtp-connections.md) (Active connection);
  [organizations/data-model/smtp-connections.md](../organizations/data-model/smtp-connections.md#at-most-one-active-connection);
  [persistence/connection.md](../persistence/connection.md).
- **Settled:** `deactivate`, database-level constraint, `PATCH` racing `activate` (see
  [Open items](#open-items)).

## Building block: test email

- **Responsibility:** `POST /smtp-connections/:id/test.json`: validate `to` (default: the
  caller's own email; must be the email of a user of the organization), load the given
  connection once (active or not; the snapshot used for the whole send), apply the
  test-email limit (window and lease in `smtp_test_limits`, short locked transaction, no lock
  during the send, lease cleared after it), run the send flow
  ([transport.md](transport.md#building-block-send-flow), including the host check) with the
  fixed test content, and answer `201`, `429` with `Retry-After`, or the `SMTP_*` error. It
  never changes connection data. Migration `0011_create_smtp_test_limits.sql` creates the
  limit table.
- **Proposed names:** `Pidgey\Http\Handler\SmtpConnectionTestHandler`, the fixed content in
  `Pidgey\Email\TestEmail`, the limit in `Pidgey\Repository\SmtpTestLimitRepository` (with a
  small `Pidgey\Email\TestEmailLimit` applying the window and lease), the host check in
  `Pidgey\Smtp\HostPolicy` (see [transport.md](transport.md#building-block-send-flow)).
  Proposals only.
- **Collaborators:** the send flow, `Pidgey\Repository\SmtpConnectionRepository`,
  `Pidgey\Repository\UserRepository` (the caller's email, the organization-user check of
  `to`), `Pidgey\Repository\SmtpTestLimitRepository`, `Pidgey\Infra\Transaction`,
  `Pidgey\Infra\WallClock` (the limit window is stored in the database),
  `Pidgey\Http\Validator`, the operator log.
- **Governing specs:** [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsidtestjson);
  [email/send-flow.md](../email/send-flow.md) (steps 5–8);
  [email/failures.md](../email/failures.md);
  [organizations/data-model/smtp-test-limits.md](../organizations/data-model/smtp-test-limits.md);
  [api/conventions/errors.md](../api/conventions/errors.md#too-many-requests).
- **Settled:** rate limit (worker exhaustion, arbitrary `to`), host check and DNS rebinding,
  edited or deleted during the test (see [Open items](#open-items)).

## Building block: operator log

- **Responsibility:** one line per successful state change (`smtp-connection-create`,
  `-update`, `-delete`, `-activate`, `-deactivate`) and one per test attempt that reached the send
  (`smtp-connection-test` with the outcome), holding the user id, organization id, action,
  connection id and, for updates, the changed field **names** only. Never passwords, never
  the recipient beyond what the send flow logs.
- **Format:** a Monolog `info` line, message = the action name, JSON context `userId`,
  `organizationId`, `connectionId`, `fields` (names only), through an `ErrorLogHandler` (same
  destination as today's `error_log()`); minimum level `LOG_LEVEL`.
- **Names (decided):** `Pidgey\Infra\LoggerFactory` and
  `Pidgey\Http\OperatorLog::record(string $action, int $userId, int $organizationId,
  int $connectionId, ?array $fields = null)` writing the lines (#164; `fields` only on
  update).
- **Collaborators:** `monolog/monolog`, `Pidgey\Container`, the handlers above.
- **Governing specs:** [api/endpoints/pidgey-admin.md](../api/endpoints/pidgey-admin.md#operator-log);
  [email/send-flow.md](../email/send-flow.md) (step 8);
  [architecture/backend.md](../architecture/backend.md#logging);
  [architecture/configuration.md](../architecture/configuration.md#logging).
- **Settled:** operator log destination and format (see [Open items](#open-items)).

## Implementation work

Names not marked decided above are **proposals** (see the lifecycle rules in
[implement-email.md](../implement-email.md)).

1. **Handlers and routes** — registered in `source/config/routes/organization.php`
   ([request-handler/routing.md](../request-handler/routing.md#route-files)):
   - [x] list / create / read / update / delete, activate and deactivate (#164);
   - [ ] the test email handler (#166).
2. **Validation**:
   - [x] the shared message templates (integer, range, one of a set, hostname or IP literal,
     no control characters, no line breaks) and the credential-redirection rule on `PATCH`
     (#164);
   - [ ] the organization-user rule of the test email `to` (#166).
3. **Migrations**:
   - [x] `0010_add_smtp_connections_active_unique.sql` (generated `active_organization_id`
     and its unique index `uq_smtp_connections_active_organization_id`) (#164);
   - [ ] `0011_create_smtp_test_limits.sql` (#166).
4. **Host policy** — `Pidgey\Smtp\HostPolicy` in the send flow: resolve once, denied ranges,
   connect to the checked IP ([transport.md](transport.md#building-block-send-flow)).
5. **Test-email limit** — `Pidgey\Repository\SmtpTestLimitRepository`: window, lease and
   `Retry-After`.
6. **Logger** — `Pidgey\Infra\LoggerFactory` (Monolog, `ErrorLogHandler`, `LOG_LEVEL`) wired
   in `Pidgey\Container` (landed in #162); the operator log lines:
   - [x] `smtp-connection-create`, `-update`, `-delete`, `-activate`, `-deactivate` through
     `Pidgey\Http\OperatorLog` (#164);
   - [ ] `smtp-connection-test` (#166).
7. **Configuration** — `ConfigValidator` / `bin/check-config`: `SMTP_ALLOW_PRIVATE_HOSTS`,
   `SMTP_TEST_MAX_ATTEMPTS`, `SMTP_TEST_WINDOW`, `LOG_LEVEL` (landed in #162) and their
   failure names
   ([architecture/configuration.md](../architecture/configuration.md#smtp-transport)); the
   development `.env` and the test / CI environment set `SMTP_ALLOW_PRIVATE_HOSTS=true`.
