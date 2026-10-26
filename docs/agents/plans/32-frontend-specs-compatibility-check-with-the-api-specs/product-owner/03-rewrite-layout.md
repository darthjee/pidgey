# Rewrite layout.md
Update `docs/agents/specs/frontend/layout.md`: keep the folder tree (check it against
`frontend/`), and rewrite the **Client layer** and **Current state** sections.

## Clients as they are
- `ApiClient`:
  - methods: `postJson`, `patchJson`, `deleteJson`;
  - same-origin credentials and JSON headers;
  - error body → `ApiError(status, message, code, details)`;
  - `204`/empty/non-JSON bodies parsed as `{}`;
  - `401` → refresh through `/auth/refresh.json` with the stored token, then retry once.
- `AccountsClient`: auth, account, recovery and device-authorization calls. Persists the
  refresh token through `AuthSession`.
- `AdminClient`: admin users calls.
- `AuthSession` (`localStorage` key `pidgey_refresh_token`), `AuthStatus`, `AuthEvents`,
  `LoginModalEvents`, `ApiError`, `pickDefined`.

## Target additions
- `ApiClient.getJson(path)`: same behaviour, no body (#42).
- `OrganizationClient` (#42), `SmtpConnectionsClient` (#43), `ApiTokensClient` (#44): one
  method per endpoint, composing `ApiClient` like the existing clients.
- Components never call `fetch` directly.

## Remove
- The old target list (`SessionClient`, `OrganizationsClient`).
- The sentence saying new code must not build on Citadel's `/auth/*` routes: the Citadel
  `/auth/*` and `/admin/users/*` routes are the contract. Link
  [api-compatibility.md](api-compatibility.md).

## Files to Change
- `docs/agents/specs/frontend/layout.md` — client layer and current-state rewrite.
