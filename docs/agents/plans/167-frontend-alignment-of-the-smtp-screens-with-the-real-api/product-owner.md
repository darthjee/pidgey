# Product-owner Plan: Frontend alignment of the SMTP screens with the real API

Main plan: [plan.md](plan.md)

## Shared contracts

You produce the example responses listed under "Example responses and fixtures" in
[plan.md](plan.md#example-responses-and-fixtures), with exactly those bodies, because frontend
copies them byte for byte into the fixtures. You also rely on frontend delivering:

- `SmtpConnectionsClient.deactivate`;
- the two form rules;
- `429` in the test modal;
- `text-break` wrapping;
- the help text.

## Implementation Steps

### Step 1 — Example responses in the API spec

In `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md`:

- **List:** under `## GET /smtp-connections.json`, add a fenced `json` example of the list
  response, with the items `12` (active) and `13` from plan.md.
- **`400`:** under `## Field rules`, add the `400 VALIDATION_FAILED` example after the message
  templates paragraph.
- **Test email:** under `## POST /smtp-connections/:id/test.json`, after the failure paragraph,
  add the `502 SMTP_AUTH_FAILED` example (with `error.smtp`) and the
  `409 SMTP_INSECURE_CREDENTIALS` example.
- **`429`:** under `### Test-email limit`, add the `429 TOO_MANY_REQUESTS` example and keep the
  note that `Retry-After` is a header, not part of the body.
- **`404`:** under `## Errors summary`, add the `404 NOT_FOUND` example.

Each example uses the envelope of `api/conventions/errors.md` and the timestamp
`2026-10-25T12:00:00.000Z`. Add one sentence near the top of the file saying that the frontend
fixtures under `frontend/specs/support/fixtures/smtpConnections/` copy these examples, so a
change to an example updates its fixture in the same PR.

### Step 2 — Frontend specs and implementation notes

- **`docs/agents/specs/frontend/testing.md#api-response-fixtures`:**
  - add the test-email success (`testSent.json`) to the shape list;
  - name each fixture file and the `loadFixture(name)` helper in
    `frontend/specs/support/loadFixture.js`.
- **`docs/agents/specs/frontend/api-compatibility.md#pidgey-admin`:**
  - remove M7, M8 and M9 from the bullet list and from the create, update, deactivate and test
    rows;
  - mark those rows ✅, with their behavior in the parentheses: deactivate (no body,
    confirmation, list reloaded); create and update (both new rules); test (`429` shown as
    `error.message`, `Retry-After` ignored, replies wrapped with `text-break`, help text).
  - Do this once frontend's changes match.
- **`docs/agents/specs/implement-email/frontend.md`:**
  - turn the "Implementation work" items 1–6 into ticked checkboxes;
  - keep item 7 (manual check) unticked, with a note that it is a pre-merge checklist run by the
    owner once #166 (test-email endpoint) is merged.

## Files to Change

- `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md` — the new example
  responses and the fixture sync sentence.
- `docs/agents/specs/frontend/testing.md` — the fixture list, file names and loader.
- `docs/agents/specs/frontend/api-compatibility.md` — M7–M9 cleared, rows ✅.
- `docs/agents/specs/implement-email/frontend.md` — items 1–6 ticked, item 7 left open with a
  note.

## Notes

- There is no CI for docs. Check that every relative link still resolves.
- The examples must match the fixtures byte for byte. If one needs to change, change both, and
  tell frontend.
