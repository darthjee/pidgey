# Terminology

Purpose: define the domain terms used consistently across all specs and code.

| Term | Definition |
| --- | --- |
| **Organization** | The tenant. Owns users, SMTP connections and API tokens. Created by the first user who registers. |
| **User** | A person who logs in to the admin UI with username/password. Belongs to exactly one organization. All users of an organization have equal access (no roles). |
| **SMTP Connection** | The settings (host, port, encryption, credentials, from address) Pidgey uses to deliver email on behalf of an organization. Organization-scoped. |
| **API Token** | A secret credential sent as `Authorization: Bearer <token>`. Belongs to an organization, carries a set of scopes, is stored hashed, and can be revoked. |
| **Scope** | A permission carried by an API token (e.g. `send`, `admin`). See [api/authentication.md](../api/authentication.md). |
| **Send Request** | A single call to `POST /v1/messages` asking Pidgey to deliver one message synchronously through the organization's active SMTP connection. |
| **Outcome** | The result of a send request returned in the API response: success, or a categorized failure (see [email/failures.md](../email/failures.md)). |
