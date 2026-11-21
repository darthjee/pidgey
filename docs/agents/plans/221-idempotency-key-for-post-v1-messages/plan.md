# Plan: Idempotency-Key for POST /v1/messages

Issue: [221-idempotency-key-for-post-v1-messages.md](../../issues/221-idempotency-key-for-post-v1-messages.md)

## Overview

Add an optional `Idempotency-Key` header to `POST /v1/messages`. The backend stores, per organization and for 24 hours, the answer of every send that may have delivered the message, and replays it on a repeat. A reused key with another payload, or a key still in flight, gets a new conflict code. `product-owner` documents the contract in the API, email, data-model and configuration docs, and updates the Node client specs (epic #219) so the client sends one key per `send()`.

**Depends on #237** (Implement `POST /v1/messages`): the backend key step plugs into that handler, so the backend work is implemented and merged after #237. The docs work does not depend on it.

## Agents involved

- [product-owner](product-owner.md)
- [backend](backend.md)

## Shared contracts

### Request and response headers

- Request: `Idempotency-Key`, optional. Valid value: 1 to 255 characters, each in `0x21`–`0x7E` (regex `/^[\x21-\x7E]{1,255}$/D`). Header absent → no idempotency handling at all.
- Invalid value (present but empty, over 255 characters, any other character) → `400 VALIDATION_FAILED`, `details: ["Idempotency-Key must be 1 to 255 printable ASCII characters"]`.
- Response on a replay: `Idempotent-Replayed: true`. Never sent on a non-replayed answer.

### New error codes

| Code | Status | Fixed message |
| --- | --- | --- |
| `IDEMPOTENCY_KEY_REUSED` | 422 | `Idempotency-Key was already used with a different payload` |
| `IDEMPOTENCY_KEY_IN_USE` | 409 | `A request with this Idempotency-Key is still in progress` |

### Table `idempotency_keys` (migration `0012_create_idempotency_keys.sql`)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `BIGINT NOT NULL AUTO_INCREMENT` | PK |
| `organization_id` | `BIGINT NOT NULL` | FK `organizations(id)` `ON DELETE RESTRICT` |
| `key_hash` | `CHAR(64) NOT NULL` | lowercase hex SHA-256 of the raw key |
| `request_hash` | `CHAR(64) NOT NULL` | lowercase hex SHA-256 of the normalized payload |
| `state` | `ENUM('in_flight','completed') NOT NULL` | |
| `response_status` | `SMALLINT NULL` | set when `completed` |
| `response_body` | `MEDIUMTEXT NULL` | the JSON body, set when `completed` |
| `lease_until` | `DATETIME NOT NULL` | reservation time + 5 minutes |
| `expires_at` | `DATETIME NOT NULL` | reservation time + 24 hours |
| `created_at`, `updated_at` | `DATETIME` | same defaults as other tables |

Keys: `UNIQUE KEY uq_idempotency_keys_organization_id_key_hash (organization_id, key_hash)`, `KEY idx_idempotency_keys_organization_id_expires_at (organization_id, expires_at)`, `CONSTRAINT fk_idempotency_keys_organization_id`.

### Constants and normalization

- TTL `86400` seconds (24 hours); lease `300` seconds (5 minutes); cleanup batch `100` rows. Code constants, no environment variables.
- `request_hash` = SHA-256 of `json_encode(['to' => …, 'subject' => …, 'text' => …, 'html' => …], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)`, fields in this order, an absent field as `null`, values as received after validation.
- `bin/check-config`: `SMTP_TOTAL_TIMEOUT` (effective value) must be below `300`; failure line `SMTP_TOTAL_TIMEOUT: not below the idempotency lease (300)`.

### Flow (after payload validation, only when the header is present)

1. Invalid key → `400`.
2. Look up (`organization_id`, `key_hash`), ignoring an expired row:
   - different `request_hash` (any state) → `422 IDEMPOTENCY_KEY_REUSED`;
   - `completed` → replay `response_status` + `response_body` verbatim (including its original `timestamp`), header `Idempotent-Replayed: true`; no send, no `smtp-send-attempt` line;
   - `in_flight`, `lease_until` in the future → `409 IDEMPOTENCY_KEY_IN_USE`;
   - `in_flight`, lease passed → complete it as the stored `504 SMTP_TIMEOUT` envelope, then replay it;
   - absent or expired → reserve (`INSERT`, or overwrite the expired row) as `in_flight`, delete at most 100 expired rows of the organization, continue.
3. Connection resolution, send-time checks and SMTP transaction as today.
4. **Store** (`completed`) when the answer is `200`, `504 SMTP_TIMEOUT`, `502 SMTP_PROTOCOL_ERROR`, or a `500 INTERNAL_ERROR` raised after the SMTP transaction started. **Release** (delete the row) on every other answer: the connection `409`s (`NO_SMTP_CONNECTION`, `SMTP_INSECURE_CREDENTIALS`, `SMTP_HOST_NOT_ALLOWED`, `SMTP_PASSWORD_UNREADABLE`) and `SMTP_CONNECTION_FAILED`, `SMTP_TEMPORARY_FAILURE`, `SMTP_REJECTED`, `SMTP_AUTH_FAILED`, `SMTP_TLS_FAILED`.
- A duplicate-key error on the reservation `INSERT` (a concurrent request won the race) re-reads the row and applies step 2.

### Attempt log

`smtp-send-attempt` gains `idempotency`: `none` (no header) or `reserved`. The raw key is never logged.

### Client specs (product-owner only, consumed later by epic #219)

- Library: always sends `Idempotency-Key: crypto.randomUUID()`, once per `send()`, reused across retries; option `idempotencyKey` (same format, else `CLIENT_CONFIGURATION`); result field `replayed: boolean`.
- CLI: `--idempotency-key <key>`.
- `409 IDEMPOTENCY_KEY_IN_USE`: never retried, `possiblySent: true`. `422 IDEMPOTENCY_KEY_REUSED`: never retried, `possiblySent: false`. Default retry policy unchanged.
