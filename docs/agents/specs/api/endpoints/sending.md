# Sending Endpoints

Purpose: define the public sending API (`POST /v1/messages`) and its contract.

## Relation to the conventions

The public sending API keeps its **own `/v1` contract**. These are documented exceptions to
[conventions.md](../conventions.md):

| Convention | `POST /v1/messages` |
| --- | --- |
| Root-relative `*.json` path, no prefix | `/v1` prefix, **no `.json` suffix** ([format.md](../conventions/format.md#paths)) |
| Cookie session through the gate | **Bearer API token** with scope `send` ([authentication/api-tokens.md](../authentication/api-tokens.md)); no gate, no admin check |
| `201` default for `POST` | Success **`200`** |

What it shares with every other endpoint:

- the **error envelope** and codes of [conventions/errors.md](../conventions/errors.md),
  including `400 VALIDATION_FAILED` with `details` and the `SMTP_*` codes with `error.smtp`;
- **`camelCase`** field names (the payload fields `to`, `subject`, `text`, `html` already
  are);
- the **origin check** ([conventions/origin-check.md](../conventions/origin-check.md)): server
  callers send no `Origin` and pass it; browser callers from another origin are rejected by
  design.

## `POST /v1/messages`

Auth: `Authorization: Bearer <token>`, scope `send`.

Request:

```json
{ "to": "user@example.com", "subject": "Hello", "text": "Hi!", "html": "<p>Hi!</p>" }
```

Payload rules (fields, required ones, multipart building) are in
[email/message-scope.md](../../email/message-scope.md). The `From:` header and envelope sender
are the active connection's `fromAddress`.

Steps: the synchronous [send flow](../../email/send-flow.md) — origin check, token
authentication (below), scope check, payload validation, active connection resolution, SMTP
transaction, answer, log.

Success **`200`**:

```json
{ "status": "sent", "smtp": { "code": 250, "message": "OK: queued as ..." } }
```

`smtp` is the SMTP server's final reply. The test email of an SMTP connection answers the
same shape (with `201`, see
[pidgey-admin/smtp-connections.md](pidgey-admin/smtp-connections.md#post-smtp-connectionsidtestjson)).

### Token authentication

The token is accepted only when all of these hold (see
[authentication/api-tokens.md](../authentication/api-tokens.md#checks)):

1. the `Authorization` header carries a well-formed Bearer token;
2. its SHA-256 matches a row of `api_tokens`;
3. the token is not revoked (`revoked_at IS NULL`);
4. the token's organization has **at least one `approved` user**.

Any failure answers the **same** `401 UNAUTHORIZED` (same status, code and message as a
revoked token), so a caller cannot tell why the token was refused.

- **Organization without an approved user:** when every user of the token's organization is
  `pending` or `denied`, its tokens are refused. **No data changes** (`revoked_at` stays
  unset), so the tokens work again as soon as a user of the organization is approved (or a
  future reinstatement happens). This settles the "denied user vs API tokens" decision of
  [organizations/entities.md](../../organizations/entities.md#user-status-lifecycle).
- `last_used_at` is updated only on a successful authentication.
- An `access_token` cookie is ignored on `/v1`.

Then a valid token lacking scope `send` answers `403 FORBIDDEN`.

### Errors

| Case | Answer |
| --- | --- |
| Cross-site browser request | `403 FORBIDDEN` — `Cross-site request rejected` |
| Missing, malformed, unknown or revoked token, or organization with no approved user | `401 UNAUTHORIZED` |
| Token without scope `send` | `403 FORBIDDEN` |
| Invalid payload | `400 VALIDATION_FAILED` |
| No active SMTP connection | `409 NO_SMTP_CONNECTION` |
| SMTP failure | `502` / `503` / `504` with the `SMTP_*` code and, when available, `error.smtp` ([email/failures.md](../../email/failures.md)) |
| Unexpected error | `500 INTERNAL_ERROR` |

## Edge cases

- The organization's only user is `pending` or `denied` → `401` with a token that is not
  revoked; approving the user makes the same token work again.
- The organization's active connection was deleted → `409 NO_SMTP_CONNECTION`
  ([pidgey-admin/smtp-connections.md](pidgey-admin/smtp-connections.md#delete-smtp-connectionsidjson)).

## Testable rules

The implementation must cover at least:

- `200` with the `{ status, smtp }` body on success;
- `401` for missing, unknown and revoked tokens and for tokens of an organization without an
  approved user, all with the same body; no `revoked_at` change in the last case;
- `403` for a token without scope `send`;
- the error envelope on every failure, including `409 NO_SMTP_CONNECTION` and the `SMTP_*`
  codes.

## Pending decisions

Size limits, idempotency keys, rate limits and CORS for browser clients are tracked in
[api/pending.md](../pending.md).
