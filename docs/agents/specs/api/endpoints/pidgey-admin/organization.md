# Organization Endpoints

Purpose: define `GET /organization.json` and `PATCH /organization.json`, the read and update
of the caller's organization.

Shared rules (access, guards, field rules, operator log) are in
[pidgey-admin.md](../pidgey-admin.md). The `organizations` table is defined in
[organizations/data-model/organizations-and-users.md](../../../organizations/data-model/organizations-and-users.md#organizations).
Errors use [conventions/errors.md](../../conventions/errors.md).

## Organization item

```json
{
  "id": 3,
  "name": "Bob's Shop",
  "createdAt": "2026-10-17T12:00:00.000Z",
  "updatedAt": "2026-10-20T09:30:00.000Z"
}
```

| Field | Content |
| --- | --- |
| `id` | `organizations.id`. |
| `name` | `organizations.name`, as stored (after trimming). |
| `createdAt`, `updatedAt` | `organizations.created_at` / `updated_at`, ISO 8601 UTC with milliseconds. |

The organization is always the caller's own (`organization_id` read by the gate); there is no
`{id}` in the path and no way to address another organization.

## `GET /organization.json`

Reads the caller's organization.

Success: **`200`**, `{ "organization": <organization item> }`.

| Case | Answer |
| --- | --- |
| Not authenticated, or user not `approved` | gate's `401` |
| Success | `200 { "organization": … }` |

## `PATCH /organization.json`

Renames the caller's organization.

Request:

```json
{ "name": "Bob's Bigger Shop" }
```

| Field | Rules |
| --- | --- |
| `name` | **required**; string, not empty (after trimming), max 255 characters |

- Same rule as registration's `organizationName`
  ([auth/session.md](../auth/session.md)): names are **not unique**, and no "name taken"
  error exists.
- `name` absent or `null` → `400 VALIDATION_FAILED` (`name should not be empty`).
- Unknown fields (including `id`) are dropped.

Success: **`200`**, `{ "organization": <organization item> }`, reloaded after the update.

- A `name` identical (byte-wise) to the stored one is not an update: the answer is `200` with
  the unchanged item, and no operator log line is written.
- Otherwise one [operator log line](../pidgey-admin.md#operator-log) with the action
  `organization-update`, the organization id and the changed field name (`name`), never the
  value.

| Case | Answer |
| --- | --- |
| Cross-site request | `403 FORBIDDEN` — `Cross-site request rejected` |
| Not authenticated, or user not `approved` | gate's `401` |
| Validation failure | `400 VALIDATION_FAILED` |
| Success | `200 { "organization": … }` |

## Edge cases

- A whitespace-only `name` → `400 VALIDATION_FAILED` (`name should not be empty`).
- A 256-character `name` (after trimming) → `400 VALIDATION_FAILED`
  (`name must be shorter than or equal to 255 characters`).
- Surrounding whitespace is trimmed before storing (`"  Shop  "` → `"Shop"`).
- An instance admin reads and renames **their own** organization only, like any member.
- A `pending` or `denied` user gets the gate's `401` (their organization is kept, unused).

## Testable rules

The implementation must cover at least:

- `GET` and `PATCH` return the item shape above; `PATCH` returns the reloaded item;
- the gate's `401` for anonymous callers, before validation; no admin check (a non-admin
  approved user succeeds);
- the organization comes from the session, never from the body;
- `name` trimming, required and 255-character rules;
- an identical name writes no operator log line; a change writes one, with no value.

## Pending decisions

None.
