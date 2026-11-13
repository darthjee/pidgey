# Email Plan: Send flow: MIME, host policy and failure mapping

Main plan: [plan.md](plan.md)

## Shared contracts

See [plan.md#shared-contracts](plan.md#shared-contracts); read-only reviewer.

## Implementation Steps

### Step 1 — Review the MIME builder

Check `MimeBuilder` and its tests against RFC 5322, RFC 2045, RFC 2046 and RFC 2047:
- header order;
- encoded-word length and character boundaries;
- folding;
- QP soft and hard line breaks;
- boundary format;
- part order.

### Step 2 — Review the outcome mapping

Check `SendFlow` / `SmtpFailureException` against `failures.md`:
- step-based variants;
- port hints;
- `error.smtp` presence;
- reply text joining, redaction and sanitization.

Report findings to the coordinator. Make no edits.
