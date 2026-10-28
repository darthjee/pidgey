# Pidgey Admin Endpoints

Purpose: index of the Pidgey-only admin endpoint specs (organization, SMTP connections, API
tokens) and the rules they all share (access, guards, `:id` handling, field rules, operator
log).

These endpoints follow the Citadel conventions ([conventions.md](../conventions.md)) and the
authentication model ([authentication.md](../authentication.md)). Organization isolation is
defined in [organizations/scope.md](../../organizations/scope.md). Errors use
[conventions/errors.md](../conventions/errors.md).

| File | Content |
| --- | --- |
| [organization.md](pidgey-admin/organization.md) | `GET /organization.json`, `PATCH /organization.json`. |
| [smtp-connections.md](pidgey-admin/smtp-connections.md) | `/smtp-connections*.json`: list, create, read, update, delete, activate, test email. |
| [api-tokens.md](pidgey-admin/api-tokens.md) | `/api-tokens*.json`: list, create (plain token returned once), revoke. |

## Endpoints

| Method & path | Purpose | Success |
| --- | --- | --- |
| `GET /organization.json` | Read the caller's organization | `200` |
| `PATCH /organization.json` | Update it | `200` |
| `GET /smtp-connections.json` | List connections | `200` |
| `POST /smtp-connections.json` | Create a connection | `201` |
| `GET /smtp-connections/:id.json` | Read one | `200` |
| `PATCH /smtp-connections/:id.json` | Update one | `200` |
| `DELETE /smtp-connections/:id.json` | Delete one | `204` |
| `POST /smtp-connections/:id/activate.json` | Make it the active connection | `200` |
| `POST /smtp-connections/:id/test.json` | Send a test email through it | `201` |
| `GET /api-tokens.json` | List tokens | `200` |
| `POST /api-tokens.json` | Create a token (plain token returned once) | `201` |
| `DELETE /api-tokens/:id.json` | Revoke a token | `204` |

- Reads with no body use `GET`. The "reads as `POST`" rule of
  [conventions/format.md](../conventions/format.md#paths) applies only when a read needs a
  body, which none of these does.
- The public sending API (`POST /v1/messages`) is not part of this surface: it keeps its own
  `/v1` contract ([sending.md](sending.md)).

## Access

- Every route goes through the [gate](../authentication/gate.md#the-gate): a valid cookie
  session of an `approved` user. Anyone else, including a `pending` caller, gets the gate's
  uniform `401`.
- **No admin check:** any approved user of the organization has the same access (the equal
  access policy of [organizations/entities.md](../../organizations/entities.md#equal-access-policy)).
- An instance admin has this access over **their own organization only**, like any other
  member; there is no cross-organization access to organizations, SMTP connections or API
  tokens (see [organizations/scope.md](../../organizations/scope.md)).
- The organization is always the authenticated user's `organization_id`, **as read by the
  gate**, never request input. Every query filters by it.
- Resources of another organization answer `404`, with the same body as an unknown id, as if
  they did not exist.

## Guards

Every route goes through, in this order (see
[authentication/gate.md](../authentication/gate.md#guard-order)):

1. **Origin check** on `POST`, `PATCH` and `DELETE`
   ([conventions/origin-check.md](../conventions/origin-check.md)); `GET` routes skip it.
2. **Gate** (above).
3. **`:id` parsing** on the routes that take one (below).
4. **Body validation** ([conventions/validation.md](../conventions/validation.md)).
5. **Handler** (including the organization-scoped lookup).

An anonymous caller with an invalid `:id` or body gets the gate's `401`, never a `400`.

## `:id` path parameter

The routes `/smtp-connections/:id…` and `/api-tokens/:id.json` take the resource id in the
path.

| `:id` | Answer |
| --- | --- |
| Does not match `^[1-9][0-9]*$` (e.g. `0`, `-1`, `01`, `1.5`, `abc`) | `400 BAD_REQUEST` — `Validation failed (numeric string is expected)` |
| Well-formed but no such resource, out of range for the column (e.g. a huge number), or a resource of another organization | `404 NOT_FOUND` — `SMTP connection not found` / `API token not found` |

The format check runs after the gate and before body validation; the lookup runs in the
handler.

## Shared rules

- String fields are **trimmed** before validation, except secrets (the SMTP `password`),
  which are never trimmed. "Not empty" and length limits are checked after trimming.
- An optional field that is **absent or `null`** is "not given". A present but invalid value
  is `400 VALIDATION_FAILED`.
- **Unknown fields are dropped** ([conventions/validation.md](../conventions/validation.md)),
  including `id`, `organizationId` and timestamps: no endpoint changes them through a body
  field.
- Validation failures answer `400 VALIDATION_FAILED` with one `details` entry per failed
  rule, all reported at once ([conventions/errors.md](../conventions/errors.md#validation-errors)).
- Field names are `camelCase`; dates are ISO 8601 UTC with milliseconds
  ([conventions/format.md](../conventions/format.md#content-type)).
- **Lists** are ordered by `id` ascending and are **not paginated** in v1: they are small and
  organization-scoped (see the pagination item in [api/pending.md](../pending.md)).

## Operator log

Every **successful** state change writes **one line** to the application (operator) log:

| Action | Route |
| --- | --- |
| `organization-update` | `PATCH /organization.json` |
| `smtp-connection-create` | `POST /smtp-connections.json` |
| `smtp-connection-update` | `PATCH /smtp-connections/:id.json` |
| `smtp-connection-delete` | `DELETE /smtp-connections/:id.json` |
| `smtp-connection-activate` | `POST /smtp-connections/:id/activate.json` |
| `smtp-connection-test` | `POST /smtp-connections/:id/test.json` |
| `api-token-create` | `POST /api-tokens.json` |
| `api-token-revoke` | `DELETE /api-tokens/:id.json` |

Each line holds:

- the acting user's `users.id`;
- the organization's `organizations.id`;
- the action (above);
- the resource id (the organization, connection or token id);
- for updates, the **names** of the changed fields (e.g. `host`, `password`), never their
  values.

It **never** includes SMTP passwords, the plain API token or its hash. The test email logs its
outcome (sent, or the `SMTP_*` code), not the recipient's address beyond what the
[send flow](../../email/send-flow.md) already logs. Reads (`GET`) write **no** operator log
line.

The same rule applies to every `500` path of these endpoints: what is logged identifies the
route, the acting user's id, the organization id and the error class or code, never request
body values, passwords or tokens.

## Pending decisions

Each detail file lists its own:

- [organization.md](pidgey-admin/organization.md#pending-decisions);
- [smtp-connections.md](pidgey-admin/smtp-connections.md#pending-decisions) (a `deactivate`
  endpoint, a database-level "at most one active" constraint, an SMTP host deny-list /
  allow-list);
- [api-tokens.md](pidgey-admin/api-tokens.md#pending-decisions).
