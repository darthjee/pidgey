# Frontend / API Compatibility

Purpose: record which API endpoints the admin UI calls, through which client method, and
whether the call matches the API spec.

## How it was checked

- **Frontend side:** the clients in `frontend/assets/js/client/` (`ApiClient`,
  `AccountsClient`, `AdminClient`, `OrganizationClient`, `SmtpConnectionsClient`, `AuthStatus`) and the routes in
  `frontend/assets/js/utils/routing/HashRouteResolver.js`. Components never call `fetch`
  directly (see [layout.md](layout.md#client-layer)), so the clients are the full list of calls.
- **API side:** the endpoint specs in [api/endpoints](../api/endpoints.md) and the shared rules
  they link to (conventions, authentication).
- Each row compares the path, the method, the request and response field names, and the status
  handling.
- **Success codes:** the frontend only checks for a 2xx answer (`response.ok`), so `200`, `201`
  and `204` are interchangeable for it. The `Success` column still records the exact code from
  the spec, so a backend change can be checked against it.
- Status: ✅ when the call matches the spec; otherwise the mismatch id (M1–M6, as in the issue
  that introduced this file) and its follow-up issue. When the frontend is wrong, the spec
  stays and a frontend follow-up is opened; no API spec was found wrong in this check.

## Auth and session

| Method & path | Client method | Spec | Success | Status |
| --- | --- | --- | --- | --- |
| `POST /auth/register.json` | `AccountsClient.register` | [session.md](../api/endpoints/auth/session.md#post-authregisterjson) | `201` | **M1**: `organizationName` is required but not sent, so every registration answers `400 VALIDATION_FAILED` (#40). `password_confirmation` is sent and dropped as an unknown field. |
| `POST /auth/login.json` | `AccountsClient.login` | [session.md](../api/endpoints/auth/session.md#post-authloginjson) | `201` | ✅ |
| `POST /auth/refresh.json` | `ApiClient` refresh path (`handleUnauthorized`) and `AccountsClient.refresh` | [session.md](../api/endpoints/auth/session.md#post-authrefreshjson) | `201` | ✅ for the shape (`refreshToken` in, `user` + `refreshToken` out). **M2**: no single flight, so parallel `401`s re-present a rotated token and trigger replay revocation (#41) |
| `DELETE /auth/logoff.json` | `AccountsClient.logout` | [session.md](../api/endpoints/auth/session.md#delete-authlogoffjson) | `204` | ✅ (empty body parsed as `{}`; the stored token is cleared even when the call fails) |
| `POST /auth/status.json` | `AccountsClient.status`, through `AuthStatus.refresh` | [session.md](../api/endpoints/auth/session.md#post-authstatusjson) | `201` | ✅ (`loggedIn`, `isAdmin`, `status: "pending"` handled) |

## Account and recovery

| Method & path | Client method | Spec | Success | Status |
| --- | --- | --- | --- | --- |
| `POST /auth/recover.json` | `AccountsClient.recover` | [recovery.md](../api/endpoints/auth/recovery.md#post-authrecoverjson) | `200` | ✅ |
| `POST /auth/reset-password.json` | `AccountsClient.resetPassword` | [recovery.md](../api/endpoints/auth/recovery.md#post-authreset-passwordjson) | `200` | ✅ (`password_confirmation` is sent and dropped as an unknown field; the confirmation check is client-side) |
| `PATCH /auth/account.json` | `AccountsClient.updateAccount` | [account.md](../api/endpoints/auth/account.md#patch-authaccountjson) | `200` | ✅ (`currentPassword` always; `username`, `email`, `newPassword` only when defined) |

## Device authorization

| Method & path | Client method | Spec | Success | Status |
| --- | --- | --- | --- | --- |
| `POST /auth/authorization-requests.json` | `AccountsClient.createAuthorizationRequest` | [requester.md](../api/endpoints/auth/device-authorization/requester.md#post-authauthorization-requestsjson) | `201` | ✅ |
| `POST /auth/authorization-requests/{uuid}/poll.json` | `AccountsClient.pollAuthorizationRequest` | [requester.md](../api/endpoints/auth/device-authorization/requester.md#post-authauthorization-requestsuuidpolljson) | `201` | ✅ (`open`, `approved`, `denied`, `expired`, `logged`; the token is stored on `approved`) |
| `POST /auth/authorization-requests/mine.json` | `AccountsClient.listAuthorizationRequests` | [approver.md](../api/endpoints/auth/device-authorization/approver.md#post-authauthorization-requestsminejson) | `201` | ✅ |
| `POST /auth/authorization-requests/{uuid}/authorize.json` | `AccountsClient.authorizeAuthorizationRequest` | [approver.md](../api/endpoints/auth/device-authorization/approver.md#post-authauthorization-requestsuuidauthorizejson) | `201` | ✅ |
| `POST /auth/authorization-requests/{uuid}/deny.json` | `AccountsClient.denyAuthorizationRequest` | [approver.md](../api/endpoints/auth/device-authorization/approver.md#post-authauthorization-requestsuuiddenyjson) | `201` | ✅ |

## Admin users

The UI ignores the `organizationId` and `organizationName` fields of the
[admin user item](../api/endpoints/admin-users.md#admin-user-item); showing them is the
optional follow-up #45. This is cosmetic and does not change the status below.

| Method & path | Client method | Spec | Success | Status |
| --- | --- | --- | --- | --- |
| `POST /admin/users/search.json` | `AdminClient.searchUsers` | [review.md](../api/endpoints/admin-users/review.md#post-adminuserssearchjson) | `201` | ✅ (`q`, `status` only when defined; `users`, `currentUserId` read) |
| `POST /admin/users/{id}/edit.json` | `AdminClient.editUser` | [edit.md](../api/endpoints/admin-users/edit.md#post-adminusersideditjson) | `201` | ✅ |
| `POST /admin/users/{id}/approve.json` | `AdminClient.approveUser` | [review.md](../api/endpoints/admin-users/review.md#post-adminusersidapprovejson) | `200` | ✅ |
| `POST /admin/users/{id}/deny.json` | `AdminClient.denyUser` | [review.md](../api/endpoints/admin-users/review.md#post-adminusersiddenyjson) | `200` | ✅ |
| `POST /admin/users/{id}/recovery-link.json` | `AdminClient.generateRecoveryLink` | [recovery.md](../api/endpoints/admin-users/recovery.md#post-adminusersidrecovery-linkjson) | `201` | ✅ |
| `POST /admin/users/{id}/send-recovery-email.json` | `AdminClient.sendRecoveryEmail` | [recovery.md](../api/endpoints/admin-users/recovery.md#post-adminusersidsend-recovery-emailjson) | `201` | ✅ |

## Pidgey admin

The organization endpoints are called by `OrganizationClient` from the Organization screen
(#42), the SMTP connection endpoints by `SmtpConnectionsClient` from the SMTP connections
screens (#43), and the API token endpoints by `ApiTokensClient` from the API tokens screens
(#44, see [screens.md](screens.md#api-tokens-screens)); `GET` requests go through
`ApiClient.getJson`.

| Method & path | Client method | Spec | Success | Status |
| --- | --- | --- | --- | --- |
| `GET /organization.json` | `OrganizationClient.get` | [organization.md](../api/endpoints/pidgey-admin/organization.md#get-organizationjson) | `200` | ✅ (`GET` with no body, through `ApiClient.getJson`; `organization.name` read) |
| `PATCH /organization.json` | `OrganizationClient.update` | [organization.md](../api/endpoints/pidgey-admin/organization.md#patch-organizationjson) | `200` | ✅ (only `name` sent; `VALIDATION_FAILED` details shown on the name field) |
| `GET /smtp-connections.json` | `SmtpConnectionsClient.list` | [smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#get-smtp-connectionsjson) | `200` | ✅ (`GET` with no body; `smtpConnections` read, the active one flagged by `active`) |
| `POST /smtp-connections.json` | `SmtpConnectionsClient.create` | [smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsjson) | `201` | ✅ (only the accepted fields that are defined; `active` never sent; numeric `port`; `username` / `password` only when filled) |
| `GET /smtp-connections/{id}.json` | `SmtpConnectionsClient.get` | [smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#get-smtp-connectionsidjson) | `200` | ✅ (`GET` with no body; `404` shown as "not found"; `hasPassword` read, no password returned) |
| `PATCH /smtp-connections/{id}.json` | `SmtpConnectionsClient.update` | [smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#patch-smtp-connectionsidjson) | `200` | ✅ (only the accepted fields that are defined; `active` never sent; `password` only when typed; `""` for both credentials to remove them; `VALIDATION_FAILED` details mapped to fields) |
| `DELETE /smtp-connections/{id}.json` | `SmtpConnectionsClient.remove` | [smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#delete-smtp-connectionsidjson) | `204` | ✅ (empty body parsed as `{}`) |
| `POST /smtp-connections/{id}/activate.json` | `SmtpConnectionsClient.activate` | [smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsidactivatejson) | `200` | ✅ (no request body; the returned item marks the active row) |
| `POST /smtp-connections/{id}/test.json` | `SmtpConnectionsClient.test` | [smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsidtestjson) | `201` | ✅ (`{ to }` only when a recipient is typed, otherwise `{}`; `smtp.code` / `smtp.message` shown on success; `SMTP_*` failures show `error.smtp` when present) |
| `GET /api-tokens.json` | `ApiTokensClient.list` | [api-tokens.md](../api/endpoints/pidgey-admin/api-tokens.md#get-api-tokensjson) | `200` | ✅ (`GET` with no body; `apiTokens` read in the API order, revoked ones flagged by `revokedAt`) |
| `POST /api-tokens.json` | `ApiTokensClient.create` | [api-tokens.md](../api/endpoints/pidgey-admin/api-tokens.md#post-api-tokensjson) | `201` | ✅ (only `name` (trimmed) and `scopes` sent; the plain `token` shown once and never stored; `VALIDATION_FAILED` details mapped to the name / scopes fields) |
| `DELETE /api-tokens/{id}.json` | `ApiTokensClient.revoke` | [api-tokens.md](../api/endpoints/pidgey-admin/api-tokens.md#delete-api-tokensidjson) | `204` | ✅ (no request body; empty body parsed as `{}`; the list is reloaded) |

## Operational

| Method & path | Client method | Spec | Success | Status |
| --- | --- | --- | --- | --- |
| `GET /health.json` | not called by the frontend | [operational.md](../api/endpoints/operational.md#get-healthjson) | `200` | n/a (probe for infrastructure) |
| `GET /ready.json` | not called by the frontend | [operational.md](../api/endpoints/operational.md#get-readyjson) | `200` / `503` | n/a (probe for infrastructure) |

## Sending

| Method & path | Client method | Spec | Success | Status |
| --- | --- | --- | --- | --- |
| `POST /v1/messages` | not called by the frontend | [sending.md](../api/endpoints/sending.md#post-v1messages) | `200` | n/a (public API for integrations, Bearer token; the test email goes through `POST /smtp-connections/{id}/test.json`) |

## Cross-cutting behaviour

| Behaviour | Status |
| --- | --- |
| `401` → refresh once (`POST /auth/refresh.json` with the stored token), retry the original request once; a `401` on the retry, a failed refresh or no stored token ends the session (token cleared, login modal opened) | ✅ |
| Single-flight refresh, within a tab and across tabs (rule in [screens.md](screens.md#refresh-single-flight)) | **M2**, #41 |
| `403` on admin screens → redirect home | ✅ |
| Error body parsing: `error.code`, `error.message`, `error.details` and, on `SMTP_*` failures, `error.smtp` (`{ code, message }`) into `ApiError` (see [api/conventions/errors.md](../api/conventions/errors.md)); falls back to `statusText`, then a generic message | ✅ |
| `204` and empty or non-JSON bodies parsed as `{}` | ✅ |
| camelCase field names (`refreshToken`, `pollToken`, `currentPassword`, `newPassword`, `isAdmin`, `currentUserId`, …) | ✅ (the snake_case `password_confirmation` is dropped by the API as an unknown field) |
| `access_token` cookie sent with `credentials: 'same-origin'`; root-relative `*.json` paths with no `/v1` prefix | ✅ |
| Refresh token in `localStorage` (`pidgey_refresh_token`) | **M3**, kept for v1 as a known limitation (see [screens.md](screens.md#refresh-token-storage)) |
| Dev server proxies `*.json` paths to the backend | Vite proxies only `/v1` today, so the auth and admin calls do not reach the backend in development (#46; see [development.md](development.md)) |

## Pending decisions

None.
