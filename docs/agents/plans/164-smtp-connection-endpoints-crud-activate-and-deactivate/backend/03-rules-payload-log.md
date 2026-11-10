# Connection rules, payload, path id and operator log

- `Pidgey\Http\SmtpConnectionRules`:
  - `forCreate(array $body): array` — declares the field set (name, host, port, encryption,
    username, password, fromAddress; strings trimmed except `password`; `""` / `null` / absent
    username or password = `null`), then pairing and credentials-require-encryption;
  - `forUpdate(array $body, SmtpConnection $stored): SmtpConnectionChange` — every field
    optional (absent / `null` = unchanged; `""` clears username / password; `""` or
    whitespace on a required field → `should not be empty`), merges with `$stored`, checks
    pairing and encryption on the merged state, then credential redirection: merged state has
    credentials, `host` / `port` / `encryption` changed, and no non-empty `password` → `400`.
  - **Change detection:** trimmed value `!==` stored value (exact, case-sensitive); a present
    non-empty `password` is always a change. `SmtpConnectionChange` exposes the merged values,
    the new plain password (if any) and the changed field names (API names, e.g. `fromAddress`).
  - All `details` reported at once; cross-field rules run only when field rules pass.
- `Pidgey\Http\SmtpConnectionPayload` (`JsonSerializable`): the item of the spec;
  `hasPassword = encryptedPassword !== null`; dates ISO 8601 UTC with milliseconds (reuse the
  existing date formatter); never `password` / `organizationId`.
- `Pidgey\Http\PathId::parse()` per the shared contract.
- `Pidgey\Http\OperatorLog` per the shared contract, built from `Container::logger()`.
- Unit tests: every field rule and edge case of the spec's Edge cases list; merged-state
  pairing / encryption; redirection (host / port / encryption change with and without
  password, clearing both, same host re-sent, case change counts); payload shape; `PathId`
  (`0`, `-1`, `01`, `1.5`, `abc`, huge); log line context via `CapturedLog`.

## Files to Change
- `source/lib/Http/SmtpConnectionRules.php`, `SmtpConnectionChange.php` — new.
- `source/lib/Http/SmtpConnectionPayload.php` — new.
- `source/lib/Http/PathId.php` — new.
- `source/lib/Http/OperatorLog.php` — new; `source/lib/Container.php` — `operatorLog()`.
- `source/tests/unit/Http/*` — tests.
