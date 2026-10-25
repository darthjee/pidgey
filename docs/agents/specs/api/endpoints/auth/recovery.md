# Recovery Endpoints

Purpose: define self-service password recovery: `POST /auth/recover.json`,
`POST /auth/reset-password.json`, password-reset tokens and the recovery email.

Shared rules (guards, trimming, password hashing) are in [auth.md](../auth.md). Tokens are
stored in `password_reset_tokens`
([organizations/data-model/password-reset-tokens.md](../../../organizations/data-model/password-reset-tokens.md)).
Settings (`PASSWORD_RESET_TOKEN_TTL`, `PASSWORD_RESET_COOLDOWN`, `FRONTEND_BASE_URL`) are in
[architecture/configuration.md](../../../architecture/configuration.md). Errors use
[conventions/errors.md](../../conventions/errors.md).

## `POST /auth/recover.json`

Public. Starts a self-service password reset.

Request:

```json
{ "email": "bob@example.com" }
```

| Field | Rules |
| --- | --- |
| `email` | valid email (after trimming) |

Success: **always `200`**, `{ "sent": true }`, whether or not the email exists and whether or
not an email was actually sent. Validation failures answer `400 VALIDATION_FAILED`.

Side effects:

| Case | Effect |
| --- | --- |
| Unknown email, or user `denied` | Nothing: no token, no email. |
| User `approved` or `pending`, with a token created less than `PASSWORD_RESET_COOLDOWN` seconds ago (**cooldown**) | Nothing: no new token, no email. |
| User `approved` or `pending`, otherwise | Create a [password-reset token](#password-reset-tokens) and send the [recovery email](#recovery-email) **synchronously**, before answering. |

- The cooldown uses `password_reset_tokens.created_at` of the user's most recent token
  (used or not); no extra table.
- Sending is **inline**: Pidgey runs PHP synchronously and nothing runs after the response
  (as in [email/send-flow.md](../../../email/send-flow.md)).
- Every send failure (no sender configured, connection, TLS, authentication, rejection,
  timeout) is **logged and swallowed**: the answer is still `200 { "sent": true }`, never an
  `SMTP_*` code or `error.smtp` (see
  [conventions/errors.md](../../conventions/errors.md#smtp-send-failures)).
- The **status, body and log line** are the same in every case (unknown, denied, cooldown,
  sent, not sent, failed), so the endpoint cannot be used to learn which emails are
  registered. Send failures are additionally logged server-side for operators; they never
  reach the response. A `pending` user stays `pending`.

**Known limitation (timing):** the response time is not equalised. A request for an
`approved` or `pending` user's email that is not in cooldown includes an SMTP round trip,
the others answer instantly, so the latency reveals whether the email belongs to such a user.
The SMTP connect and command timeouts of the send flow bound that response time. Sending
after flushing the response (`Connection: close` + `ignore_user_abort`) was rejected as
fragile under Apache mod_php (output buffering, compression, proxies) and hard to test; an
outbox closing the leak is a pending decision below.

Throttling: besides the per-user cooldown, there is no application-level throttle in v1
(per-IP throttling is pending in [api/pending.md](../../pending.md)).

## `POST /auth/reset-password.json`

Public. Completes the reset with the token taken from the email link.

Request (the frontend also sends `password_confirmation`, which is dropped as an unknown
field):

```json
{ "token": "a1b2…(96 hex chars)", "password": "newpass123" }
```

| Field | Rules |
| --- | --- |
| `token` | string, not empty |
| `password` | string, min 8 characters, max 72 bytes (never trimmed) |

Success: **`200`**, `{ "reset": true }`.

Steps, in order:

1. Look up the token by its SHA-256 hash. Not found, `used_at` set, or `expires_at <= now`
   → `400 BAD_REQUEST` — `Invalid or expired token`.
2. Load the user. Missing or `denied` → the **same** `400`. This check happens **before**
   hashing the new password, and the token is **not** consumed.
3. Hash the new password (see [Password hashing](../auth.md#password-hashing)).
4. In **one transaction**:
   1. consume the token **conditionally**:
      `UPDATE … SET used_at = ? WHERE id = ? AND used_at IS NULL AND expires_at > ?`
      (`now` bound from PHP). If it affects **0 rows** (a concurrent reset used the token, or
      it expired in between), roll back and answer the same `400`: nothing is changed;
   2. update the user's `password_digest`;
   3. revoke every active refresh token of the user with reason `password_reset` (see
      [sessions.md](../../authentication/sessions.md#revocation-reasons)).
5. Answer `200 { "reset": true }`.

| Case | Answer |
| --- | --- |
| Validation failure | `400 VALIDATION_FAILED` |
| Token unknown, used or expired; user missing or `denied`; token lost to a concurrent reset | `400 BAD_REQUEST` — `Invalid or expired token` |
| Valid | `200 { "reset": true }` |

- The answer is never `401`, so the frontend does not try to refresh.
- A `pending` user stays `pending` after a reset.
- A reset does **not** clear an account-edit lockout
  ([account.md](account.md#lockout)).
- Other outstanding tokens of the user stay valid until they expire or are used.

## Password-reset tokens

The same mechanism is used by recover and by the admin recovery link / send-recovery-email
(#30).

- Token: **48 bytes from a CSPRNG, hex-encoded (96 characters)**.
- **Stored only as a SHA-256 hex hash** in `password_reset_tokens.token_hash`; the plain value
  is never stored or logged.
- Lifetime: `expires_at = created_at + PASSWORD_RESET_TOKEN_TTL` (default `1800` seconds,
  30 minutes), both taken from PHP's clock (see
  [sessions.md](../../authentication/sessions.md#clock)).
- **Single use:** a successful reset sets `used_at`; a used token is dead.
- Issuing a new token does **not** invalidate the user's other outstanding tokens.
- Link: `{FRONTEND_BASE_URL}/#/recover-password?token={token}`.

## Recovery email

The recovery email is a **system email**: it is sent by Pidgey itself, not on behalf of an
organization.

- **Sender:** the **active SMTP connection of the system organization**, the one organization
  marked as the instance's own. There are no `SYSTEM_SMTP_*` settings, and the user's own
  organization's connection is not used (a fresh organization has none).
- It goes through the **same SMTP client** as the [send flow](../../../email/send-flow.md)
  (timeouts, TLS policy, failure categories of [email/failures.md](../../../email/failures.md)),
  with `From:` and envelope sender set to the connection's `from_address`.
- **Content:** plain text.
  - Subject: `Reset your Pidgey password`.
  - Body: a greeting, the link `{FRONTEND_BASE_URL}/#/recover-password?token={token}`, and a
    note that the link is single-use, expires after `PASSWORD_RESET_TOKEN_TTL`, and can be
    ignored if the user did not ask for it.
- **Not sent:** a logged no-op that reports "not sent" when there is no system organization,
  it has no active SMTP connection, or `FRONTEND_BASE_URL` is empty (the link would be
  broken). Recover still answers `200 { "sent": true }`; the "not sent" result only matters
  to the admin send-recovery-email (#30).

**Security consideration:** every member of the system organization can change its SMTP
connection, and can therefore redirect or read the password-reset emails of the whole
instance. The system organization is trusted like the admin
([organizations/entities.md](../../../organizations/entities.md#admin)); to be revisited with
the setup flow.

## Testable rules

The implementation must cover at least: identical responses (status, body, log line) for
unknown, denied, cooldown and sent cases; the cooldown boundary; failures swallowed with
`200`; the concurrent reset with the same token (one `200`, one `400`, a single password
change); a denied user's token left unconsumed; the 72-byte boundary on `password`.

## Pending decisions

- **Pending decision:** how the system organization is marked and who marks it, deferred to
  the setup flow (with the first admin, see
  [organizations/bootstrap.md](../../../organizations/bootstrap.md)). For example an
  `is_system` flag on `organizations`, at most one, set by the setup flow. No column is added
  yet; until it is decided there is no system organization and recovery emails are not sent.
- **Pending decision:** an outbox table and a CLI worker (or cron) sending system emails
  asynchronously, which would close the recover timing leak. It also applies to the admin
  send-recovery-email (#30).
- **Pending decision (#30):** whether the admin send-recovery-email bypasses the
  `PASSWORD_RESET_COOLDOWN` cooldown (leaning: yes).
