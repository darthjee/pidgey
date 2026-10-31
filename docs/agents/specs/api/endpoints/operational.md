# Operational Endpoints

Purpose: define the liveness and readiness probes, `GET /health.json` and `GET /ready.json`.

Both routes are **public**: no gate, no admin check, and, being `GET`, no origin check (see
the guard order in [conventions/format.md](../conventions/format.md#guard-order)). They send no
cache headers: Pidgey has no caching proxy in front of the API
([conventions/format.md](../conventions/format.md#cache-headers)).

| Method & path | Purpose | Success |
| --- | --- | --- |
| `GET /health.json` | Liveness | `200` |
| `GET /ready.json` | Readiness | `200` / `503` |

## `GET /health.json`

Liveness: the PHP application answers. It **touches nothing** (no database, no SMTP, no
file).

Always **`200`**:

```json
{ "status": "ok" }
```

## `GET /ready.json`

Readiness: the application can serve requests. It runs `SELECT 1` against the database.

| Database | Answer |
| --- | --- |
| OK | `200` `{ "status": "ok", "checks": { "database": "up" } }` |
| Failing (connection error, query error) | `503` `{ "status": "error", "checks": { "database": "down" } }` |

- The `503` body is this probe body, **not** the error envelope of
  [conventions/errors.md](../conventions/errors.md).
- The failure reason (exception class and message) is **logged**, never returned. The log
  line never includes DSN credentials or stack-trace arguments.

## Scope

- Wiring these probes into Docker or deployment health checks is out of scope (see
  [docker.md](../../docker.md) and [deployment.md](../../deployment.md)); the deploy's post-release
  smoke check is manual (see
  [deployment/release.md](../../deployment/release.md#verification)).
- No SMTP check: SMTP connections belong to organizations and are tested per connection
  ([pidgey-admin/smtp-connections.md](pidgey-admin/smtp-connections.md#post-smtp-connectionsidtestjson)).

## Edge cases

- Anonymous request, or a request with an invalid cookie or Bearer header → served normally
  (credentials are ignored).
- `/ready.json` with the database down → `503`, no error envelope, reason logged.

## Testable rules

The implementation must cover at least:

- `/health.json` answers `200 { "status": "ok" }` without touching the database (also with the
  database down);
- `/ready.json` answers `200` with the database up and `503` with it down, with the bodies
  above and no failure detail in the response; the logged reason contains no DSN credentials;
- neither route requires authentication.

## Pending decisions

None.
