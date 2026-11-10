# Endpoint tests

Integration tests through the front controller (the existing endpoint test base), covering
every testable rule of `smtp-connections.md` except the test-email ones (#166):

- success code and body shape of each endpoint; `password` absent from every response;
  `hasPassword` reflecting the stored state (also for undecryptable bytes on list / read);
- gate `401` for anonymous calls before `:id` parsing and validation; `403` cross-site
  writes; malformed `:id` `400`; unknown, huge and foreign `:id` `404` on every route;
- field rules with exact messages, merged-state pairing and encryption, credential
  redirection (exact message; name / fromAddress / username alone allowed);
- created connection always inactive, `active` in a body ignored on create and update;
- activate idempotent, deactivate on the target only and idempotent, delete of the active
  connection leaves none;
- create ordering: the stored password decrypts with the new row's associated data; an
  encryption failure (container with an unusable key) leaves no row and answers `500`;
- update without `password` keeps the stored bytes, with one they change;
- operator log lines for every state change via `CapturedLog`, field names only, none on a
  no-op `PATCH`;
- canary: a distinctive password never appears in any response body or captured log line.

## Files to Change
- `source/tests/integration/Http/Handler/SmtpConnectionsHandlerTest.php`,
  `SmtpConnectionHandlerTest.php`, `SmtpConnectionActivationTest.php` — new.
