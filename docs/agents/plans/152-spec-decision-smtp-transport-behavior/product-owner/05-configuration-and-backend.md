# Configuration and backend layers

- `architecture/configuration.md`: an "SMTP transport" settings table with
  `SMTP_CONNECT_TIMEOUT` (10), `SMTP_COMMAND_TIMEOUT` (10), `SMTP_TOTAL_TIMEOUT` (25),
  `SMTP_CA_FILE` (empty), `SMTP_EHLO_NAME` (empty); rules (per-step timeouts
  `<= SMTP_TOTAL_TIMEOUT`, CA file readable, EHLO name LDH); the new `bin/check-config`
  failure names (`SMTP_CA_FILE: not readable`, `SMTP_EHLO_NAME: invalid hostname`, and one
  name for the timeout ordering rule, e.g. `SMTP_CONNECT_TIMEOUT: exceeds SMTP_TOTAL_TIMEOUT`);
  boot sets `zend.exception_ignore_args=1` outside `development` / `test` (Decision 9).
- `architecture/backend.md`: layers table per Decision 11 (feature namespaces
  `Pidgey\Auth\`, `Pidgey\Email\`; `Pidgey\Smtp\` row kept; no `Pidgey\Service\`); the
  dependency rule; remove the transport pending decision (Decision 10).

## Files to Change
- `docs/agents/specs/architecture/configuration.md` — new keys, rules, check-config names.
- `docs/agents/specs/architecture/backend.md` — layers, dependency rule, pending removed.
