# SMTP connection endpoints

Create `api/endpoints/pidgey-admin/smtp-connections.md` (issue Solution, sections 4, 5, 6
and 9):

- the item shape `{ id, name, host, port, encryption, username, hasPassword, fromAddress,
  active, createdAt, updatedAt }`; `password` write-only, `hasPassword` boolean;
- field rules for `name`, `host`, `port`, `encryption`, `username`, `password`,
  `fromAddress` (lengths, enum, no whitespace/control characters, no CR/LF/NUL, valid email);
  `username`/`password` both set or both empty, checked on the merged state;
- `GET /smtp-connections.json` (`200 { smtpConnections }`), `POST` create (`201`, always
  inactive, `active` ignored), `GET /{id}.json` (`200`), `PATCH /{id}.json` (`200`; absent
  or `null` unchanged, `""` clears `username`/`password`, required fields cannot be cleared),
  `DELETE /{id}.json` (`204`, hard delete, allowed on the active connection);
- `POST /smtp-connections/{id}/activate.json`: one transaction locking the organization row
  (`SELECT … FOR UPDATE`), deactivate the others, activate the target; idempotent `200`;
- `POST /smtp-connections/{id}/test.json`: body `{ to? }` defaulting to the caller's email;
  fixed subject and text; synchronous through the send flow on the given connection
  (active or not); `201 { status: "sent", smtp }`; failures with the `SMTP_*` codes and
  `error.smtp`; `NO_SMTP_CONNECTION` never applies;
- the SSRF known limitation (user-chosen host and port; error categories as a reachability
  oracle) and the slow-server known limitation;
- pending decisions: `deactivate` endpoint, database constraint for one active connection,
  host deny-list / allow-list, test email rate limit (link to `api/pending.md`);
- edge cases (password pairing, concurrent activations, deleting the active connection,
  test on an inactive or foreign connection) and testable rules.

## Files to Change
- `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md` — new.
