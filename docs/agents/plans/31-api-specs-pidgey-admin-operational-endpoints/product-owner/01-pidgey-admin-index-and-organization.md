# Pidgey admin index and organization

Create `api/endpoints/pidgey-admin.md`, a short index, with the rules shared by every
Pidgey admin endpoint (issue Solution, sections 2, 3 and 9):

- the endpoint table (paths, purpose, success codes) linking to the three detail files;
- access: gate (cookie session, `approved` user), **no admin check**, equal access; an admin
  has this access over their own organization only; the organization comes from the
  gate-read `organization_id`, never from input; other organizations' resources → `404`;
- `{id}` parsing (`^[1-9][0-9]*$` → else `400 BAD_REQUEST` "Validation failed (numeric
  string is expected)"; unknown/out of range/other organization → `404` with the
  per-resource message);
- shared field rules: trimming, absent or `null` = not given, unknown fields ignored,
  `400 VALIDATION_FAILED` with one `details` entry per failed rule;
- lists ordered by `id` ascending, unpaginated (link to `api/pending.md`);
- the operator log line (user id, organization id, action, resource id, changed field
  names; never passwords, plain tokens or hashes);
- pending decisions pointing to the detail files.

Create `api/endpoints/pidgey-admin/organization.md`:

- `GET /organization.json` → `200 { organization }` with `{ id, name, createdAt, updatedAt }`;
- `PATCH /organization.json`, body `{ name }`: required, 1–255 characters after trimming,
  not unique (same rule as registration's `organizationName`) → `200 { organization }`;
  operator log on change;
- edge cases and testable rules.

## Files to Change
- `docs/agents/specs/api/endpoints/pidgey-admin.md` — new index.
- `docs/agents/specs/api/endpoints/pidgey-admin/organization.md` — new.
