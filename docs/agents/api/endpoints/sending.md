# Sending Endpoints

## Relation to the conventions

The public sending API keeps its **own `/v1` contract**. These are documented exceptions to
[conventions.md](../conventions.md):

| Convention | `POST /v1/messages` |
| --- | --- |
| Root-relative `*.json` path, no prefix | `/v1` prefix, **no `.json` suffix** ([format.md](../conventions/format.md#paths)); the only `/v1` path with the suffix is the public [`/v1/openapi.json`](openapi.md) |
| Cookie session through the gate | **Bearer API token** with scope `send` ([authentication/api-tokens.md](../authentication/api-tokens.md)); no gate, no admin check |
| `201` default for `POST` | Success **`200`** |

The contract of this endpoint is also published as the OpenAPI document served at
[`GET /v1/openapi.json`](openapi.md). `/v1` is **additive-only** (see
[openapi.md](openapi.md#v1-is-additive-only)): a change here is made in the same change set
as the document, and a breaking change needs a `/v2`.

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
[email/message-scope.md](../../email/message-scope.md). An empty-string `text` or `html`
counts as **not sent**; when neither is sent the answer is `400 VALIDATION_FAILED` with the
detail `text or html is required`. The `From:` header and envelope sender
are the active connection's `fromAddress`.

### Size limits

- **Body:** at most **1 MiB** for the whole request, a limit shared by every route and
  enforced by the body parser
  ([request-handler/request-response/request.md](../../request-handler/request-response/request.md#json-body)).
  A larger body answers `413 HTTP_413` (`Content Too Large`) when the body is read (after the guards, before validation).
- **`subject`:** at most **255 characters** (UTF-8 characters, not bytes); a longer subject
  answers `400 VALIDATION_FAILED` with `subject must be shorter than or equal to 255 characters`.
- **`text` / `html`:** no field-level cap; they are bounded only by the 1 MiB body limit. A
  message that fits the body limit but is too large for the SMTP server still fails at the
  server (`552`, answered as `502 SMTP_REJECTED`, see [email/failures.md](../../email/failures.md)).

Why: the `subject` cap keeps the header well inside RFC 5322's line limits (see
[email/message-scope.md](../../email/message-scope.md#headers)); the body limit is the single
bound on the rest of the payload.

Steps: the synchronous [send flow](../../email/send-flow.md) — origin check, token
authentication (below), scope check, payload validation, the
[`Idempotency-Key` step](#idempotency-key) (only when the header is present), active
connection resolution, SMTP transaction, answer, log.

Success **`200`**:

```json
{ "status": "sent", "smtp": { "code": 250, "message": "OK: queued as ..." } }
```

`smtp` is the SMTP server's final reply. The test email of an SMTP connection answers the
same shape (with `201`, see
[pidgey-admin/smtp-connections.md](pidgey-admin/smtp-connections.md#post-smtp-connectionsidtestjson)).

### Idempotency-Key

Optional request header that lets a caller retry after losing the response without sending
the message twice. Without the header the endpoint behaves as before and touches no
idempotency storage.

- **Format:** 1 to 255 characters, each printable ASCII (`0x21`-`0x7E`); a UUID fits. A
  present but empty, over-long or otherwise invalid value answers `400 VALIDATION_FAILED`
  with `details: ["Idempotency-Key must be 1 to 255 printable ASCII characters"]`.
- **Scope:** per **organization**, not per token: a retry with another token of the same
  organization finds the stored answer, and organizations never see each other's keys.
- **TTL:** **24 hours** (`86400` seconds) from the first request; an expired row is treated
  as absent. Expired rows are cleaned lazily (see [email/send-flow.md](../../email/send-flow.md#idempotency-key-step)).
- **Replay:** the response of a replay carries **`Idempotent-Replayed: true`** (never sent on
  a non-replayed answer). The stored `response_status` and `response_body` are replayed
  **verbatim**, including the original `timestamp` of an error envelope.

Flow, after payload validation and only when the header is present:

1. Invalid key: `400 VALIDATION_FAILED`.
2. Look up the key of the organization, ignoring an expired row:
   - different payload hash (any state): `422 IDEMPOTENCY_KEY_REUSED`;
   - `completed`: replay the stored answer with `Idempotent-Replayed: true`; no send and no
     `smtp-send-attempt` log line (independent of the current connection state);
   - `in_flight` with the lease (**5 minutes**) in the future:
     `409 IDEMPOTENCY_KEY_IN_USE`;
   - `in_flight` with the lease passed: completed as the stored `504 SMTP_TIMEOUT` envelope
     (delivery unknown), then replayed;
   - absent or expired: reserved as `in_flight` (deleting at most **100** expired rows of the
     organization), and the request continues.
3. Connection resolution, send-time checks and SMTP transaction as without a key.
4. **Store** (the row becomes `completed`) when the answer is `200`, `504 SMTP_TIMEOUT`,
   `502 SMTP_PROTOCOL_ERROR`, or a `500 INTERNAL_ERROR` raised after the SMTP transaction
   started. **Release** (the row is deleted, so a retry with the same key really sends) on
   every other answer: the connection `409`s (`NO_SMTP_CONNECTION`,
   `SMTP_INSECURE_CREDENTIALS`, `SMTP_HOST_NOT_ALLOWED`, `SMTP_PASSWORD_UNREADABLE`) and
   `SMTP_CONNECTION_FAILED`, `SMTP_TEMPORARY_FAILURE`, `SMTP_REJECTED`, `SMTP_AUTH_FAILED`,
   `SMTP_TLS_FAILED`.

A concurrent request that wins the reservation race makes the loser re-read the row and
apply step 2.

What counts as "the SMTP transaction started": the send flow was entered and the answer is not
a send-time check refusal. So a send-time `409` (`SMTP_INSECURE_CREDENTIALS`,
`SMTP_HOST_NOT_ALLOWED`, `SMTP_PASSWORD_UNREADABLE`) is released, while an unexpected exception
raised inside the send flow is answered and stored as `500 INTERNAL_ERROR`, because delivery
cannot be ruled out. The error answers of a reserved key are rendered by the handler itself,
so the stored body is exactly the body sent.

Errors answered before the key step (`400` for the payload, `401`, `403`, `413`, `415`) never
touch the stored keys.

**Limit:** the key removes duplicates caused between the caller and Pidgey only. A
`504 SMTP_TIMEOUT` or `502 SMTP_PROTOCOL_ERROR` leaves Pidgey itself unsure of delivery; SMTP
has no idempotency, so such a stored result is replayed, never re-sent.

Why per organization, and why the message content is never stored (only a hash of the
normalized payload, to detect a reused key): see
[email/send-flow.md](../../email/send-flow.md#idempotency-key-step).

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
- `last_used_at` is updated on a successful authentication, including when the scope check
  then answers `403`; it is never updated for a `401`.
- An `access_token` cookie is ignored on `/v1`.

Then a valid token lacking scope `send` answers `403 FORBIDDEN`.

### Errors

| Case | Answer |
| --- | --- |
| Cross-site browser request | `403 FORBIDDEN` — `Cross-site request rejected` |
| Missing, malformed, unknown or revoked token, or organization with no approved user | `401 UNAUTHORIZED` |
| Token without scope `send` | `403 FORBIDDEN` |
| Malformed JSON or a body that is not a JSON object | `400 BAD_REQUEST` — `Bad Request` |
| Body over 1 MiB | `413 HTTP_413` — `Content Too Large` |
| `Content-Type` is not `application/json` | `415 HTTP_415` — `Unsupported Media Type` |
| Invalid payload (including a `subject` over 255 characters) | `400 VALIDATION_FAILED` |
| Invalid `Idempotency-Key` (empty, over 255 characters or a non-printable-ASCII character) | `400 VALIDATION_FAILED` — `details: ["Idempotency-Key must be 1 to 255 printable ASCII characters"]` |
| `Idempotency-Key` already used with a different payload | `422 IDEMPOTENCY_KEY_REUSED` — `Idempotency-Key was already used with a different payload` |
| `Idempotency-Key` of a request still in progress | `409 IDEMPOTENCY_KEY_IN_USE` — `A request with this Idempotency-Key is still in progress` |
| No active SMTP connection | `409 NO_SMTP_CONNECTION` — `The organization has no active SMTP connection` |
| Active connection with credentials and `encryption: none` | `409 SMTP_INSECURE_CREDENTIALS` |
| Active connection's `host` resolves to a denied address (`SMTP_ALLOW_PRIVATE_HOSTS=false`) | `409 SMTP_HOST_NOT_ALLOWED` — `The SMTP host resolves to an address that is not allowed` ([email/send-flow.md](../../email/send-flow.md#host-check)) |
| Stored password cannot be decrypted | `409 SMTP_PASSWORD_UNREADABLE` |
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
- a `subject` of exactly 255 characters is accepted and one of 256 characters answers
  `400 VALIDATION_FAILED`; a body over 1 MiB answers `413 HTTP_413`;
- the error envelope on every failure, including `409 NO_SMTP_CONNECTION` and the `SMTP_*`
  codes;
- idempotency (see [Idempotency-Key](#idempotency-key)):
  - no header: no stored row;
  - store and replay: the repeat answers the stored answer with `Idempotent-Replayed: true`
    and makes no SMTP call;
  - the same key with a different payload answers `422 IDEMPOTENCY_KEY_REUSED`;
  - `in_flight` within the lease answers `409 IDEMPOTENCY_KEY_IN_USE`; past the lease it is
    completed as the stored `504 SMTP_TIMEOUT` and replayed;
  - a releasing outcome (e.g. `503 SMTP_TEMPORARY_FAILURE`) deletes the row, so the retry
    sends;
  - `SMTP_TIMEOUT` and `SMTP_PROTOCOL_ERROR` are stored and replayed;
  - invalid keys (empty, 256 characters, a non-printable character) answer
    `400 VALIDATION_FAILED`;
  - the same key in two organizations sends twice;
  - an expired key sends again;
  - the lazy cleanup deletes at most the bounded batch (100 rows);
  - a `409 NO_SMTP_CONNECTION` answered before the SMTP transaction stores nothing;
  - a replay still answers after the active connection was deleted;
  - `bin/check-config` rejects `SMTP_TOTAL_TIMEOUT=300`.

## Pending decisions

Rate limits and CORS for browser clients are tracked in
[api/pending.md](../pending.md). Idempotency is decided: see
[Idempotency-Key](#idempotency-key).
