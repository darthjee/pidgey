# Cross-references and pending items

Remove the temporary admin file and update every spec that refers to it or to #31:

- delete `api/endpoints/admin.md`;
- `api/endpoints.md`: replace the `admin.md` row with `pidgey-admin.md`, add
  `operational.md`, update the Purpose line;
- `api/authentication/api-tokens.md`: link the endpoints (`pidgey-admin/api-tokens.md`)
  instead of "(endpoints specified in #31)"; add the "organization with no approved user →
  `401`" check;
- `api/endpoints/auth/session.md`: link `GET /organization.json` to
  `pidgey-admin/organization.md` instead of "(#31)";
- `api/endpoints/auth/device-authorization.md`: keep the retention pending item, drop the
  "(#31)" reference;
- `organizations/entities.md`: resolve the "denying a user revokes their organization's API
  tokens" pending decision (tokens refused while the organization has no approved user; no
  `revoked_at` change), link `sending.md`;
- `organizations/scope.md`: link the Pidgey admin endpoints for SMTP connections and API
  tokens;
- `email/smtp-connections.md`: link the endpoints, mention `hasPassword` and the activate
  rules;
- `api/pending.md`: pagination (lists unpaginated in v1), test email under rate limits, new
  SSRF / SMTP host deny-list item;
- `api/conventions/errors.md`: no new specific code is planned; only check consistency
  (e.g. the `SMTP_*` "Where" column mentions the test email).

## Files to Change
- `docs/agents/specs/api/endpoints/admin.md` — removed.
- `docs/agents/specs/api/endpoints.md` — index rows.
- `docs/agents/specs/api/authentication/api-tokens.md` — links, approved-user check.
- `docs/agents/specs/api/endpoints/auth/session.md` — link.
- `docs/agents/specs/api/endpoints/auth/device-authorization.md` — drop "(#31)".
- `docs/agents/specs/organizations/entities.md` — resolve the API-token pending decision.
- `docs/agents/specs/organizations/scope.md` — links.
- `docs/agents/specs/email/smtp-connections.md` — links, `hasPassword`, activate rules.
- `docs/agents/specs/api/pending.md` — pagination, rate limits, SSRF.
- `docs/agents/specs/api/conventions/errors.md` — consistency check only.
