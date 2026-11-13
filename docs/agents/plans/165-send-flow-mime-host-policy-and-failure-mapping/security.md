# Security Plan: Send flow: MIME, host policy and failure mapping

Main plan: [plan.md](plan.md)

## Shared contracts

See [plan.md#shared-contracts](plan.md#shared-contracts); read-only reviewer.

## Implementation Steps

### Step 1 — SSRF and host policy

- Denied ranges are complete and edge-correct (IPv4-mapped included).
- The check runs before any connect, with no re-resolution.
- An IP literal skips DNS.
- Allow-flag parsing is exact (`=== 'true'`).

### Step 2 — Secret hygiene and injection

- `#[SensitiveParameter]` coverage.
- `SecretString` is wiped in `finally`.
- No secret in outcomes, logs, exception messages or JSON, including echo redaction of the
  base64 forms.
- `MimeBuilder` backstops against header injection.
- The log context excludes subject, body and credentials.

Report findings to the coordinator. Make no edits.
