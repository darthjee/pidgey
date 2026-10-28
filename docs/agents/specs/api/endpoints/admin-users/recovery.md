# Admin User Recovery Endpoints

Purpose: define the admin-driven password recovery: `POST /admin/users/:id/recovery-link.json`
and `POST /admin/users/:id/send-recovery-email.json`.

Shared rules (guards, `:id` handling, operator log) are in [admin-users.md](../admin-users.md).
Password-reset tokens and the recovery email are defined in
[auth/recovery.md](../auth/recovery.md); settings (`PASSWORD_RESET_TOKEN_TTL`,
`PASSWORD_RESET_COOLDOWN`, `FRONTEND_BASE_URL`) are in
[architecture/configuration.md](../../../architecture/configuration.md). Errors use
[conventions/errors.md](../../conventions/errors.md).

## Shared rules

- **Tokens** use the mechanism of
  [auth/recovery.md](../auth/recovery.md#password-reset-tokens): 48 bytes from a CSPRNG,
  hex-encoded (96 characters), stored only as a SHA-256 hex hash, valid for
  `PASSWORD_RESET_TOKEN_TTL`, single use. The plain token is never stored or logged.
- A token can be issued **whatever the target's status**. Redeeming it fails for a `denied`
  user (`400 BAD_REQUEST` — `Invalid or expired token`, see
  [reset-password](../auth/recovery.md#post-authreset-passwordjson)).
- Issuing a token does **not** invalidate the target's other outstanding tokens.
- Both endpoints **bypass the `PASSWORD_RESET_COOLDOWN` cooldown** of
  [recover](../auth/recovery.md#post-authrecoverjson): an admin can always issue a token. They
  do not count towards the cooldown beyond the `created_at` of the token they write (a
  self-service recover right after an admin-issued token is therefore in cooldown, as after
  any token).
- Both answer `404 NOT_FOUND` — `User not found` for an unknown id
  ([admin-users.md](../admin-users.md#id-path-parameter)).
- Empty body: any body fields are dropped.

## `POST /admin/users/:id/recovery-link.json`

Creates a fresh password-reset token for the target and returns the link, so the admin can
pass it on by hand.

Success: **`201`**:

```json
{ "resetUrl": "https://app.example.com/#/recover-password?token=a1b2…" }
```

- `resetUrl` is `{FRONTEND_BASE_URL}/#/recover-password?token={token}`.
- The link is shown to the admin on purpose, for frontend parity. This is part of the admin
  trust level ([organizations/entities.md](../../../organizations/entities.md#admin)).

| Case | Answer |
| --- | --- |
| Unknown id | `404 NOT_FOUND` — `User not found` |
| `FRONTEND_BASE_URL` empty | `409 CONFLICT` — `Recovery links are not configured`; **no token is created** |
| Otherwise | `201 { "resetUrl": "…" }` |

A successful call writes an [operator log line](../admin-users.md#operator-log) that never
includes the link or the token.

The `resetUrl` response body is a **live credential**: it is **never logged**, neither by the
request/access log nor by any error or debug handler (no response-body logging on this
route).

## `POST /admin/users/:id/send-recovery-email.json`

Creates a token and sends the recovery email to the target, reporting whether it was sent.

Success: **`201`**, `{ "sent": true }` or `{ "sent": false }`.

Steps (after the guards, `:id` parsing and the user lookup):

1. **Sending impossible by configuration**: there is no system organization, it has no
   active SMTP connection, or `FRONTEND_BASE_URL` is empty (see
   [auth/recovery.md](../auth/recovery.md#recovery-email)). Then **no token is created**; the
   reason is written to the operator log; answer `201 { "sent": false }`.
2. Otherwise:
   1. create the token;
   2. send the [recovery email](../auth/recovery.md#recovery-email) **synchronously**, before
      answering: the same content, sender (the system organization's active SMTP
      connection) and SMTP client as self-service recovery, to the target's **stored
      `users.email`**;
   3. answer `201 { "sent": true }`, or `201 { "sent": false }` on **any** SMTP failure
      (connection, TLS, authentication, rejection, timeout). On failure the token is
      **kept**, unused, and simply expires.

| Case | Answer |
| --- | --- |
| Unknown id | `404 NOT_FOUND` — `User not found` |
| Not configured (no system organization, no active connection, empty `FRONTEND_BASE_URL`) | `201 { "sent": false }`, no token |
| SMTP failure | `201 { "sent": false }`, token kept |
| Sent | `201 { "sent": true }` |

- This endpoint **never** answers an `SMTP_*` code, `error.smtp`, or a `500` because of mail
  (see [conventions/errors.md](../../conventions/errors.md#smtp-send-failures)). A database
  error is still a `500`.
- "Not sent" reasons and SMTP failures are written to the operator log, identifying the user
  by `users.id` only, **never** with the token, the link or the email address. An SMTP failure
  line records only the **failure category** (e.g. `SMTP_REJECTED`, see
  [email/failures.md](../../../email/failures.md)) and the **numeric reply code**, **never the
  server reply text**, which often echoes the recipient address.
- The recovery email does **not** go through the send-flow attempt log
  ([email/send-flow.md](../../../email/send-flow.md) step 8, which logs the recipient); it is
  logged only per the operator-log rule above.
- Every `201` writes the action's [operator log line](../admin-users.md#operator-log).
- **Known limitation:** the response time includes the SMTP round trip, bounded by the
  connect and command timeouts of the [send flow](../../../email/send-flow.md). An outbox
  sending system emails asynchronously is pending in
  [auth/recovery.md](../auth/recovery.md#pending-decisions).

## Edge cases

- Recovery for a `denied` user: the token is issued (link returned or email sent), and its
  redemption is refused with `400 Invalid or expired token`.
- Recovery for a `pending` user: the token is issued; a reset leaves the user `pending`.
- Two admin calls in a row for the same user: both create a token (no cooldown), and both
  tokens stay valid until one is used (a successful reset then marks every other token as
  used).
- Recovery-link with an empty `FRONTEND_BASE_URL`: `409`, no token.
- Send-recovery-email with an empty `FRONTEND_BASE_URL`: `201 { "sent": false }`, no token.

## Testable rules

The implementation must cover at least:

- recovery-link: `409 CONFLICT` with an empty `FRONTEND_BASE_URL` and no token created;
  `resetUrl` built from `FRONTEND_BASE_URL`;
- the cooldown bypass on both endpoints (a token created less than `PASSWORD_RESET_COOLDOWN`
  seconds ago does not prevent a new one);
- send-recovery-email: `sent: false` with no token when not configured; `sent: false` with
  the token kept on an SMTP failure; `sent: true` with the email sent to the stored
  `users.email`; never an `SMTP_*` code, `error.smtp` or a `500` because of mail;
- the target's other outstanding tokens left valid after issuing;
- a token issued for a `denied` user refused at redemption;
- operator log lines never contain the token, the link or the email address; an SMTP failure
  line holds only the failure category and the numeric reply code, never the reply text;
- no send-flow attempt log line (with the recipient) is written for the recovery email;
- the `resetUrl` is never written to any log.

## Pending decisions

None. The outbox for system emails is pending in
[auth/recovery.md](../auth/recovery.md#pending-decisions); the shared admin decisions are in
[admin-users.md](../admin-users.md#pending-decisions).
