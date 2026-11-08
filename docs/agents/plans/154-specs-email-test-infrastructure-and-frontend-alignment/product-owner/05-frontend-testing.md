# Frontend test fixtures and definition of done

Apply issue decisions 12 and 13.

- **`frontend/testing.md`:**
  - **Fixtures:** shared JSON fixtures under
    `frontend/specs/support/fixtures/smtpConnections/`, one per response shape: item, list,
    `VALIDATION_FAILED`, `SMTP_*` with `smtp`, `409`, `429`, `404`. The specs load them
    instead of building response objects inline.
  - **Source of the fixtures:** they are hand-maintained copies of the spec's example
    responses, not captured from the backend.
  - **Rule:** a PR that changes an example response in the API spec updates the matching
    fixture in the same PR.
- **`testing/definition-of-done.md`:** if it has a per-feature or frontend section, add the
  definition of done for the SMTP screens:
  - the Jasmine specs use the fixtures;
  - `api-compatibility.md` is accurate;
  - a manual end-to-end check in `make dev` against the dev `mailpit`: create, edit, activate,
    deactivate, test email (delivered and seen in the Mailpit UI), a `400` and a `502` path
    (bad host), delete.

  If the file only holds generic rules, put this list in `implement-email/frontend.md`
  instead (step 06).

## Files to Change

- `docs/agents/specs/frontend/testing.md` — shared fixtures and the sync rule.
- `docs/agents/specs/testing/definition-of-done.md` — only if it has a fitting section.
