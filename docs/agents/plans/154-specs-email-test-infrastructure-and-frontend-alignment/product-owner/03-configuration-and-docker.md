# Configuration and docker specs

Apply issue decisions 2 and 5 to the configuration and docker specs.

- **`architecture/configuration.md`:**
  - remove the `SMTP_HOST` / `SMTP_PORT` rows and their "SMTP (development and tests)"
    section;
  - add a short note that the test-only `TEST_MAILPIT_*` keys are read by the test support
    only, never by the app or `ConfigValidator` (link `email/testing.md`);
  - keep the `SMTP_CA_FILE` and `SMTP_ALLOW_PRIVATE_HOSTS` rows, adjusting their "test / CI"
    wording to the decided environment;
  - fix every anchor that pointed to `#smtp-development-and-tests`. Known:
    `implement-email.md` (Test infrastructure row) and `implement-email/testing.md`
    (governing specs). Grep for others.
- **`docker/compose.md`:**
  - add the three test instances to the service table: pinned tag, ports, env, read-only
    mount of `source/tests/fixtures/tls/` for the two TLS instances;
  - `tests` depends on the three of them, plus `mysql`;
  - `tests` env: the `TEST_MAILPIT_*` keys, `SMTP_ALLOW_PRIVATE_HOSTS=true`,
    `SMTP_CA_FILE`, `SMTP_ENCRYPTION_KEY`;
  - the dev `mailpit` gets the pinned tag;
  - the development `.env` sets `SMTP_ALLOW_PRIVATE_HOSTS=true`;
  - say whether the test instances' ports are published to the host. Recommended: not
    published, since only `tests` reaches them.
- **`docker/makefile.md`:** state that `make dev-up` is unchanged and that `make tests` /
  `make dev` start the test instances through the `tests` dependencies. Touch it only if
  the current text contradicts this.

## Files to Change

- `docs/agents/specs/architecture/configuration.md` — remove `SMTP_HOST` / `SMTP_PORT`, note
  the test-only keys.
- `docs/agents/specs/docker/compose.md` — the test instances, `tests` env and dependencies,
  pinned tags.
- `docs/agents/specs/docker/makefile.md` — only if needed.
- `docs/agents/specs/implement-email.md` — fix the configuration link in the Test
  infrastructure row.
