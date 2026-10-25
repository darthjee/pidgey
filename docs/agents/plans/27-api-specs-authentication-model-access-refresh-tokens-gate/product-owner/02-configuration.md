# Configuration spec
Add `docs/agents/specs/architecture/configuration.md`, one table of every environment variable
the app reads, with columns Setting, Default, Effect:

- the existing ones (`PHP_ENV`, `DB_*`, `SMTP_*`, log level, as found in `.env` /
  `source/lib/Infra`);
- #27's rows: `SECRET_KEY` (required, at least 32 bytes, otherwise the app refuses to boot),
  `PREVIOUS_SECRET_KEYS` (same rule per entry), `ACCESS_TOKEN_TTL` (900),
  `REFRESH_TOKEN_TTL` (604800), `USER_APPROVAL_REQUIRED` (`false`; exactly `true` enables it),
  `ALLOWED_ORIGINS`, `FRONTEND_BASE_URL`, `TRUSTED_PROXY_HOPS` (1).

The spec also states:

- names are bare and durations are in seconds;
- `.env` ships a clearly fake development key;
- #28 and #29 add their own rows.

Point the Configuration section of `architecture/backend.md` to it. In `origin-check.md`, replace
the variable-name Pending decision with a link to the configuration spec.

## Files to Change
- `docs/agents/specs/architecture/configuration.md`: new.
- `docs/agents/specs/architecture.md`: add an index row.
- `docs/agents/specs/architecture/backend.md`: the Configuration section becomes a pointer.
- `docs/agents/specs/api/conventions/origin-check.md`: resolve the naming Pending decision.
