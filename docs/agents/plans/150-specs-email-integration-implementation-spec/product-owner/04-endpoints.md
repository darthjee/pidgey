# Endpoints detail file

Create `docs/agents/specs/implement-email/endpoints.md` (owner #153).

Building blocks:
- **Connection endpoints** — `GET/POST /smtp-connections.json`,
  `GET/PATCH/DELETE /smtp-connections/:id.json`; handlers under `Pidgey\Http\Handler\`, routes
  in a new `source/config/routes/` file (proposal). Collaborators: `Gate`, `OriginCheck`,
  `Validator`, `FieldRules`, `SmtpConnectionRepository`, encryption service, operator log.
- **Activate** — `POST /smtp-connections/:id/activate.json`, one locked transaction on the
  organization row. Collaborators: `Transaction`, `OrganizationRepository`,
  `SmtpConnectionRepository`.
- **Test email** — `POST /smtp-connections/:id/test.json`, through the send flow
  (`transport.md`) on the given connection.
- **Operator log** — one line per successful state change and per test attempt, field names
  only, no secrets.
- Governing specs: `api/endpoints/pidgey-admin.md` (access, guards, `:id`, shared rules,
  operator log), `api/endpoints/pidgey-admin/smtp-connections.md`, `organizations/scope.md`,
  `api/pending.md`.

Shared contract (what it carries): the SMTP connection item (`id`, `name`, `host`, `port`,
`encryption`, `username`, `hasPassword`, `fromAddress`, `active`, `createdAt`, `updatedAt`) —
referenced from the permanent spec, not copied.

Open items (`**Pending decision:**`):
- SSRF host deny-list / allow-list and DNS rebinding (resolve once, connect to checked IP);
- credential redirection: re-send the password on `host` / `port` / `encryption` change;
- test-email rate limit (scope, storage, answer), including worker exhaustion and arbitrary
  `to`;
- `deactivate` endpoint;
- DB-level "at most one active" constraint;
- connection edited / deleted while its test email is in flight (snapshot);
- `PATCH` racing `activate` on the same connection.

## Files to Change
- `docs/agents/specs/implement-email/endpoints.md` — new detail file.
