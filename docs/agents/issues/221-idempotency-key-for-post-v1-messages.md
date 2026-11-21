# Issue: Idempotency-Key for POST /v1/messages

## Description

Add an optional `Idempotency-Key` request header to `POST /v1/messages` so a caller can retry after losing the response without sending the message twice. Pidgey stores the key per organization, with the final answer, for 24 hours, and replays that answer on a repeat. The Node client specs (epic #219) gain the matching behavior. Spun off from epic #219; independent of it (the clients ship without it).

**Depends on #237** (Implement `POST /v1/messages`): the endpoint has no handler yet, so the backend part of this issue is implemented on top of that handler and is merged after it.

## Problem

When a caller of `POST /v1/messages` loses the response (client-side timeout, connection reset after the request was sent, or a proxy answering `504` while PHP keeps sending), it cannot tell whether Pidgey already delivered the message. Retrying can deliver it twice. The Node.js client avoids this by **not** retrying ambiguous failures unless the consumer opts in with `retryAmbiguous: true`, which only trades duplicates for lost sends.

Limit: this removes duplicates caused between the caller and Pidgey only. A `504 SMTP_TIMEOUT` or `502 SMTP_PROTOCOL_ERROR` leaves Pidgey itself unsure of delivery; SMTP has no idempotency, so such a stored result is replayed, never re-sent.

## Expected Behavior

- A request without `Idempotency-Key` behaves exactly as today and touches no new table.
- A request with a valid key whose message may have been delivered stores the answer; a repeat with the same key and the same payload, from any token of the same organization, within 24 hours, gets that answer verbatim plus `Idempotent-Replayed: true`, and nothing is sent.
- The same key with a different payload answers `422 IDEMPOTENCY_KEY_REUSED`.
- The same key while the first request is still in flight answers `409 IDEMPOTENCY_KEY_IN_USE`.
- An outcome proving nothing was sent releases the key, so a retry with the same key really sends.
- An invalid key answers `400 VALIDATION_FAILED` with a `details` entry for `Idempotency-Key`.
- `pidgey-client` sends one key per `send()` call, stable across its retries; its default retry policy is unchanged.

## Solution

### Header and key format

- `Idempotency-Key`, optional, in the spirit of the IETF httpapi `Idempotency-Key` draft.
- 1 to 255 characters of printable ASCII (`0x21`–`0x7E`); a UUID fits. A present but empty, over-long or otherwise invalid value answers `400 VALIDATION_FAILED` with a `details` entry for `Idempotency-Key`.
- Scoped per **organization**, not per token: rotating a token mid-retry still finds the stored result, and organizations never see each other's keys.

### Storage

New migration creating `idempotency_keys`:

| Column | Meaning |
| --- | --- |
| `organization_id` | FK to `organizations`. |
| `key_hash` | SHA-256 of the key (fixed size, the raw key is never stored). Unique on (`organization_id`, `key_hash`). |
| `request_hash` | SHA-256 of the normalized payload (`to`, `subject`, `text`, `html`), to detect a reused key with a different payload. |
| `state` | `in_flight` or `completed`. |
| `response_status`, `response_body` | The final answer, replayed verbatim. |
| `expires_at`, `created_at`, `updated_at` | `expires_at` is 24 hours after the first request. |

- **TTL:** 24 hours (a code constant). An expired row is treated as absent and overwritten when its key comes back.
- **Cleanup:** lazy, no new process. Inserting a key also deletes at most 100 of the same organization's expired rows (`DELETE … LIMIT 100`).
- **Reservation:** one `INSERT` relying on the unique index; a duplicate-key error means the key is known and its row is read. No lock is held around the SMTP transaction; completion is one `UPDATE`.

### Position in the send flow

Origin check, token authentication, scope check and payload validation run first, unchanged. Then, when the header is present:

1. Validate the key format (`400`).
2. Look up the key for the organization:
   - `completed`, same `request_hash` → replay the stored answer with `Idempotent-Replayed: true` (independent of the current SMTP connection state);
   - any state, different `request_hash` → `422 IDEMPOTENCY_KEY_REUSED`;
   - `in_flight` within the lease, same `request_hash` → `409 IDEMPOTENCY_KEY_IN_USE`;
   - `in_flight` past the lease → completed as a stored `504 SMTP_TIMEOUT` (delivery unknown), then replayed;
   - absent or expired → reserve it as `in_flight` and continue.
3. Run the connection resolution, the send-time checks and the SMTP transaction as today.
4. Finish the row:
   - **stored** (the message may have been delivered): `200`, `504 SMTP_TIMEOUT`, `502 SMTP_PROTOCOL_ERROR`, and a `500 INTERNAL_ERROR` raised once the SMTP transaction started;
   - **released** (row deleted): every error answered before the SMTP transaction (the connection `409`s such as `NO_SMTP_CONNECTION`, `SMTP_INSECURE_CREDENTIALS`, `SMTP_HOST_NOT_ALLOWED`, `SMTP_PASSWORD_UNREADABLE`), and the outcomes proving nothing was sent: `SMTP_CONNECTION_FAILED`, `SMTP_TEMPORARY_FAILURE`, `SMTP_REJECTED`, `SMTP_AUTH_FAILED`, `SMTP_TLS_FAILED`.

Errors answered before the key step (`400`, `401`, `403`, `413`, `415`) never touch the table.

### Lease

- The `in_flight` lease is a 5-minute code constant, longer than any SMTP session.
- `bin/check-config` gains a rule: `SMTP_TOTAL_TIMEOUT` must be below `300` seconds (the lease), reported like the existing `exceeds` checks; `docs/agents/architecture/configuration.md` states the limit.

### Security and logs

- The message content is never stored (only `request_hash`); the stored body is the `{ status, smtp }` answer or the error envelope, which already carry no secret.
- The attempt log gains the idempotency outcome (for example `idempotencyReplayed`), never the raw key. A replay logs no send attempt.
- Row growth is bounded: a row needs a request that passed authentication and validation, and rows expire after 24 hours. API rate limits stay a separate pending item.

### Docs (`product-owner`)

- `docs/agents/api/endpoints/sending.md`: the header, the replay header, the flow above, the two new error rows, testable rules; the idempotency item removed from `docs/agents/api/pending.md`.
- `docs/agents/api/conventions/errors.md`: `IDEMPOTENCY_KEY_REUSED` (`422`) and `IDEMPOTENCY_KEY_IN_USE` (`409`).
- `docs/agents/email/send-flow.md`: the key step and the attempt-log field.
- `docs/agents/architecture/database.md` (if it lists tables) and `configuration.md` (the lease rule).
- The OpenAPI document gets the header and codes when it exists (`specs/node-client/openapi.md`).

### Node client specs (`product-owner`, `docs/agents/specs/node-client/`)

The client **code** follows in epic #219's sub-issues; this issue only updates the specs.

- `pidgey-client` always sends `Idempotency-Key: <crypto.randomUUID()>`, generated once per `send()` and reused across its retries. A server predating #221 ignores the header, so the "any Pidgey serving `POST /v1/messages`" promise holds.
- New `idempotencyKey` option on `send()`; a value outside the server's format is rejected locally with `CLIENT_CONFIGURATION`.
- New CLI flag `--idempotency-key <key>`.
- The success result gains `replayed` (`true` when the response carries `Idempotent-Replayed: true`).
- `409 IDEMPOTENCY_KEY_IN_USE`: never retried, `possiblySent: true`. `422 IDEMPOTENCY_KEY_REUSED`: never retried, `possiblySent: false`.
- The retry policy is unchanged: `retryAmbiguous` stays `false` by default (the client cannot detect server support). With it on, an ambiguous `NETWORK_ERROR` retry becomes duplicate-free on a supporting server, and a retry after `SMTP_TIMEOUT` gets the stored `504` replayed.
- `testing.md` rows for each rule above.

### Backend (`backend`)

- The migration, an `IdempotencyKey` repository, the key step in the `POST /v1/messages` handler (built in #237), and the `check-config` rule.
- PHPUnit covers at least: no header (no row); store and replay with `Idempotent-Replayed: true` and no SMTP call; a different payload → `422`; `in_flight` within the lease → `409`, past it → stored `504`; a releasing outcome (`503`) → the retry sends; `SMTP_TIMEOUT` and `SMTP_PROTOCOL_ERROR` stored; invalid keys (empty, 256 characters, a non-printable character) → `400`; the same key in two organizations sends twice; an expired key sends again; cleanup deletes at most the bounded batch; a pre-SMTP `409 NO_SMTP_CONNECTION` stores nothing; a replay still answers after the active connection was deleted; `check-config` rejects `SMTP_TOTAL_TIMEOUT=300`.

### Out of scope / rejected

- Persisting or queueing every message (the `messages` table question of `send-flow.md` stays open).
- A `messageId` body field instead of a header (changes the payload whitelist; confused with `Message-ID`).
- A `Message-ID` derived from the key: `Message-ID` stays random.
- Retrying ambiguous failures by default in the client.

## Benefits

- Callers can retry a lost response safely, removing the duplicate-versus-lost-send trade-off on servers that support keys.
- Fully backwards compatible: optional header, additive codes and response header, an additive migration, additive client options.
- No message content at rest and no new background process.
