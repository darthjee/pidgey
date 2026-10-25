# Data Model

Purpose: index of the database table specs; the authoritative schema is the SQL migrations (see [architecture/database.md](../architecture/database.md)).

| File | Content |
| --- | --- |
| [conventions.md](data-model/conventions.md) | Keys, foreign keys, timestamps, token hashes, charset. |
| [organizations-and-users.md](data-model/organizations-and-users.md) | `organizations`, `users`. |
| [refresh-tokens.md](data-model/refresh-tokens.md) | `refresh_tokens` (admin UI sessions). |
| [password-reset-tokens.md](data-model/password-reset-tokens.md) | `password_reset_tokens` (account recovery). |
| [authorization-requests.md](data-model/authorization-requests.md) | `authorization_requests` (device authorization). |
| [account-edit-lockouts.md](data-model/account-edit-lockouts.md) | `account_edit_lockouts` (account-edit attempt lockout). |
| [api-tokens.md](data-model/api-tokens.md) | `api_tokens`. |
| [smtp-connections.md](data-model/smtp-connections.md) | `smtp_connections`. |

`schema_migrations` is owned by the migrations runner and specified in
[architecture/database.md](../architecture/database.md).
