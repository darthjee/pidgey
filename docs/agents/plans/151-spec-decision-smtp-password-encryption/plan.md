# Plan: Spec decision: SMTP password encryption

Issue: [151-spec-decision-smtp-password-encryption.md](../../issues/151-spec-decision-smtp-password-encryption.md)

## Overview

Record the SMTP password encryption decisions of #151 in the permanent specs: a new
`email/password-encryption.md`, edits to the email, configuration, dependency, data-model and
endpoint specs, and the resolution of `implement-email/encryption.md`. Spec and doc changes
only, no code.

See [product-owner.md](product-owner.md) for the spec work.

## Architect step (deployment docs)

`docs/agents/deployment/` is owned by `architect`, not `product-owner`, so the coordinator
applies this step itself after the product-owner steps:

- `docs/agents/deployment/server.md` (shared `.env` keys table): add `SMTP_ENCRYPTION_KEY`
  (at least 32 CSPRNG bytes, e.g. `openssl rand -hex 32`; must differ from `SECRET_KEY`;
  required by `bin/check-config`) and the optional `PREVIOUS_SMTP_ENCRYPTION_KEYS`. Add to the
  "Why" paragraph that the key must be **backed up separately from the database**: losing it
  means every stored SMTP password must be re-entered.
- `docs/agents/deployment/variables.md` ("App settings are not CircleCI variables"): name the
  two SMTP keys among the settings that live only in the shared `.env`.
- `docs/agents/deployment/operations.md`: add "Rotate `SMTP_ENCRYPTION_KEY`" next to "Rotate
  `SECRET_KEY`":
  1. generate a new key (`openssl rand -hex 32`, no `,`, different from `SECRET_KEY`);
  2. edit the shared `.env`: move the current key into `PREVIOUS_SMTP_ENCRYPTION_KEYS`, set
     the new `SMTP_ENCRYPTION_KEY`;
  3. `cd <live> && $PHP_BIN bin/check-config`;
  4. `$PHP_BIN bin/reencrypt-smtp-passwords`, then `--check` until it reports no row under an
     old key and no unreadable row;
  5. remove the old key from `PREVIOUS_SMTP_ENCRYPTION_KEYS`.
  - **Leaked key:** same steps, but remove the leaked key right after step 4 instead of
    waiting.
  - Also add "Add `SMTP_ENCRYPTION_KEY` before the first release that validates it" (the
    backward-compatibility note of the issue).

## Notes

- **Correction to the issue, applied from the deployment docs:** the issue mentions
  "CircleCI deploy variables" and a "deploy" step in the rotation runbook. The deployment docs
  say app settings live **only** in the shared `.env` on the host (never CircleCI variables),
  and `public/index.php` loads `config/.env` on every request, so a key change needs **no
  deploy**. The specs and the runbook follow the deployment docs: no CircleCI variable, no
  deploy step.
- `implement-email.md` (index) and the other `implement-email/*.md` files keep their content;
  only the #151 row/status and the cross-links to `encryption.md` are touched.
- Spec files are not checked by tests; verification is PR review.
