# 04 — SMTP failure exception

Spec: `email/failures.md#fixed-messages`, `api/conventions/errors.md#smtp-send-failures`.

- `Pidgey\Http\SmtpFailureException extends HttpException`,
  `static fromOutcome(SendOutcome $outcome, int $port, SmtpEncryption $encryption): self`:
  - status and code per the table: `409` for the send-time codes, `502` / `503` / `504` for
    the transport codes;
  - messages are exact strings (copy them from `failures.md`), selected by step for
    `SMTP_REJECTED` and by situation for `SMTP_AUTH_FAILED`;
  - port hints: only on the three fixed pairs;
  - `smtp` only for `TLS_FAILED` (when a reply exists), `AUTH_FAILED` (when a reply exists),
    `REJECTED` and `TEMPORARY_FAILURE`;
  - `tlsReason` never goes into the exception;
  - a sent outcome is a `LogicException`.
- Confirm that `ErrorFormatter` already renders `HttpException::smtp()` as `error.smtp`. If
  it does not, add that.

## Files to Change

- `source/lib/Http/SmtpFailureException.php`: new.
- `source/tests/unit/Http/SmtpFailureExceptionTest.php`: a data provider over every row of
  the fixed-messages table, the auth variants, the rejected-by-step rows, the three hints
  and the non-hint pairs (for example 2525 with `ssl`); `smtp` present or absent per row;
  the formatted JSON body through `ErrorFormatter`.
