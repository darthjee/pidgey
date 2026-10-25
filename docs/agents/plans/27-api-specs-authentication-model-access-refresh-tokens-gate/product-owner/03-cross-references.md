# Cross-references and leftover wording
Remove the PHP-session and `admin`-scope wording that #27 owns:

- `docker/compose.md`: in the `app` row, "session endpoints" becomes "auth endpoints".
- `overview/components.md`: "(session cookie)" becomes the access-token cookie; "admin session
  endpoints" becomes "auth and admin endpoints".
- `overview/terminology.md`: the `Scope` example lists only `send`.
- `api/endpoints.md`: add a one-line note under the admin endpoints saying their auth is
  superseded by `authentication/` (cookie session only; #28/#31 rewrite this section). Do not
  rewrite the endpoint table.
- `frontend/development.md`: link the security note to
  `api/authentication/local-development.md`.

## Files to Change
- `docs/agents/specs/docker/compose.md`
- `docs/agents/specs/overview/components.md`
- `docs/agents/specs/overview/terminology.md`
- `docs/agents/specs/api/endpoints.md`
- `docs/agents/specs/frontend/development.md`
