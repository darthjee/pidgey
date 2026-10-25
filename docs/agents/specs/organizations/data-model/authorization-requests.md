# Authorization Requests Table

Purpose: define the `authorization_requests` table backing device authorization.

A device authorization request lets a user log in on one device by approving the request from
another, already logged-in session. The flow, lifetimes and rate limits are defined in
[api/authentication.md](../../api/authentication.md) (#27) and the device-authorization
endpoint specs (#29). Conventions are in [conventions.md](conventions.md).

## `authorization_requests`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | BIGINT | no | auto | PK |
| `uuid` | VARCHAR(36) | no | | Public id of the request; **unique** |
| `username` | VARCHAR(255) | no | | Username as typed by the requester |
| `user_id` | BIGINT | yes | null | FK → `users.id`. Resolved user; null when the username does not exist |
| `status` | ENUM(`open`,`approved`,`denied`,`logged`,`expired`) | no | `open` | Request state |
| `poll_token_hash` | CHAR(64) | no | | SHA-256 hex of the requester's poll token; **unique** |
| `request_ip` | VARCHAR(45) | no | | Requester's IP address (IPv4 or IPv6) |
| `request_user_agent` | VARCHAR(512) | no | | Requester's user agent |
| `approved_by_user_id` | BIGINT | yes | null | FK → `users.id`; indexed. User whose session approved the request |
| `created_at` | DATETIME | no | now | |
| `expires_at` | DATETIME | no | | End of the request's lifetime (configurable) |
| `resolved_at` | DATETIME | yes | null | Set on approve / deny / expire |
| `logged_at` | DATETIME | yes | null | Set when the requester's claim succeeds |
| `authorize_failed_attempts` | INT | no | `0` | Per-request counter of failed authorize attempts (lockout) |
| `authorize_locked_until` | DATETIME | yes | null | Authorize attempts on this request are refused until then |

Indexes:

- unique `uuid`;
- unique `poll_token_hash`;
- `(user_id, status)`;
- `expires_at`;
- `(request_ip, created_at)` and `(username, created_at)`, used by the rate limits;
- `approved_by_user_id`.

## Pending decisions

None.
