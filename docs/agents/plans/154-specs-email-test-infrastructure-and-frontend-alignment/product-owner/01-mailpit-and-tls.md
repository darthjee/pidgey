# Mailpit instances, keys and TLS fixtures

Rewrite `docs/agents/specs/email/testing.md` around the decided setup (issue decisions 1–3):

- **Instances:**
  - The dev `mailpit` is unchanged, and tests never send to it or clear it.
  - The three test-only instances follow the table in [plan.md](../plan.md#shared-contracts),
    each with the paths it covers:
    - `mailpit-plain`: `encryption: none` and the delivery assertions.
    - `mailpit-starttls`: `tls` with right and wrong credentials, and `530` without them.
    - `mailpit-ssl`: `ssl` with right and wrong credentials.
  - Why three: one Mailpit listener has one mode, auth applies to every send, and
    credentials require TLS.
- **How tests reach them:** the three `TEST_MAILPIT_*` keys, form `host:smtpPort:apiPort`,
  read only by `Pidgey\Tests\Support\Mailpit`. A missing key fails the test; it is never
  skipped.
- **Mailbox:** each test that inspects one clears it first (`DELETE /api/v1/messages`), and
  reads it through `GET /api/v1/messages` / `GET /api/v1/message/{id}`. Replace the current
  "cleared before each test" sentence.
- **TLS fixtures** in `source/tests/fixtures/tls/`, committed, test-only:
  - the four certificates of the contract, and the regeneration script;
  - long validity;
  - the private keys are test-only and never used outside tests.

  The test environment sets `SMTP_CA_FILE` to the test CA, and verification is **never
  disabled**. Expired certificates are not tested. The wrong-name and untrusted cases
  (`SMTP_TLS_FAILED`) run against the fake server.
- **Failure paths:** replace the "unreachable hosts / wrong credentials where Mailpit is
  configured to require auth" bullet with the decided instances and the fake server.

## Files to Change

- `docs/agents/specs/email/testing.md` — instances, keys, mailbox handling, TLS fixtures.
