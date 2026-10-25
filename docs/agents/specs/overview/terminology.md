# Terminology

Purpose: define the domain terms used consistently across all specs and code.

| Term | Definition |
| --- | --- |
| **Organization** | The tenant. Owns users, SMTP connections and API tokens. Created at registration together with its first user; never deleted in v1. See [organizations/entities.md](../organizations/entities.md). |
| **User** | A person who logs in to the admin UI. Has a unique username (to log in) and a unique email (for recovery), belongs to exactly one organization and has a user status. All users of an organization have equal access (no roles). |
| **Admin** | A user with `is_admin = true`: a global instance admin who is still a normal member of one organization. Their only extra power is managing users across the instance (search, edit, approve, deny, recovery); no access to other organizations' SMTP connections or API tokens. |
| **User status** | `pending`, `approved` or `denied`. Only `approved` users pass the gate. New users start `pending` when `USER_APPROVAL_REQUIRED=true`, otherwise `approved`. See [organizations/entities.md](../organizations/entities.md#user-status-lifecycle). |
| **Session** | One login of a user in the admin UI, identified by a session id and carried by an access token and a refresh token. See [api/authentication.md](../api/authentication.md). |
| **Access token** | Short-lived credential of a session, sent in the `access_token` cookie. See [api/authentication.md](../api/authentication.md). |
| **Refresh token** | Long-lived credential of a session, stored hashed and rotated on each use to obtain a new access token. See [api/authentication.md](../api/authentication.md). |
| **Authorization request** | Device authorization: a pending login on one device, approved from another, already logged-in session. See [api/authentication.md](../api/authentication.md). |
| **SMTP Connection** | The settings (host, port, encryption, credentials, from address) Pidgey uses to deliver email on behalf of an organization. Organization-scoped. |
| **API Token** | A secret credential sent as `Authorization: Bearer <token>` by API clients. Belongs to an organization, carries a set of scopes, is stored hashed, and can be revoked. Distinct from the access and refresh tokens of an admin UI session. |
| **Scope** | A permission carried by an API token (e.g. `send`, `admin`). See [api/authentication.md](../api/authentication.md). |
| **Send Request** | A single call to `POST /v1/messages` asking Pidgey to deliver one message synchronously through the organization's active SMTP connection. |
| **Outcome** | The result of a send request returned in the API response: success, or a categorized failure (see [email/failures.md](../email/failures.md)). |
