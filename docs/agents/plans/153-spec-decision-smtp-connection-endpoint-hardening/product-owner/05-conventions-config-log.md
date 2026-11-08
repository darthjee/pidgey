# Conventions, configuration and operator log

Decisions 8 and 9, plus the new keys of decisions 1 and 3.

- `api/conventions/validation.md`: add the shared templates `<field> must be an integer`,
  `<field> must be between <min> and <max>`, `<field> must be a hostname or an IP address`,
  `<field> must not contain control characters`, `<field> must not contain line breaks`;
  in the endpoint spec, point at them instead of listing the messages and remove the
  pending bullet.
- `architecture/configuration.md`: add `SMTP_ALLOW_PRIVATE_HOSTS` (boolean, default `false`;
  development and test env files set `true`), `SMTP_TEST_MAX_ATTEMPTS` (positive integer,
  default `10`), `SMTP_TEST_WINDOW` (positive integer seconds, default `3600`) and
  `LOG_LEVEL` (a PSR-3 level name, default `info`), with `bin/check-config` failures for
  invalid values; remove the log-level pending decision.
- `api/endpoints/pidgey-admin.md` (Operator log) and `architecture/backend.md`: the operator
  log goes through `Pidgey\Infra\LoggerFactory`, a Monolog logger with `ErrorLogHandler`
  (same destination as `error_log()`); one `info` line per action, message = the action
  name, JSON context `userId`, `organizationId`, `connectionId`, `fields` (names only).

## Files to Change
- `docs/agents/specs/api/conventions/validation.md`
- `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md`
- `docs/agents/specs/architecture/configuration.md`
- `docs/agents/specs/api/endpoints/pidgey-admin.md`
- `docs/agents/specs/architecture/backend.md`
