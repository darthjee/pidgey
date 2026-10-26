# Frontend spec docs
Update the frontend spec docs so they describe the organization client, screen and `getJson` as implemented rather than as targets. Do **not** edit `docs/agents/specs/api/**`.

- `docs/agents/specs/frontend/api-compatibility.md`:
  - In the `GET` / `PATCH /organization.json` rows, set the client column to `OrganizationClient.get` / `OrganizationClient.update` and mark them compatible.
  - Remove or adjust the "`ApiClient` has no `GET` method" sentence.
  - Narrow M6 to #43 and #44 wherever it is summarised.
- `docs/agents/specs/frontend/layout.md`: move `ApiClient.getJson` and `OrganizationClient` out of **Target additions** into the description of the existing clients. Add the #41 single-flight bullet only if it is still listed as a target and the code already does it.
- `docs/agents/specs/frontend/screens.md`:
  - Move `#/organization` out of "Pidgey screens to come" into the current screens list.
  - Document the **Organization** header dropdown (Settings → `#/organization`, visible to every logged-in, approved user), and note that #43 and #44 add their items there.

## Files to Change
- `docs/agents/specs/frontend/api-compatibility.md` — organization rows now compatible; M6 narrowed.
- `docs/agents/specs/frontend/layout.md` — `getJson` and `OrganizationClient` are no longer target additions.
- `docs/agents/specs/frontend/screens.md` — Organization screen and header dropdown.
