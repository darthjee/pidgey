# Configuration, conventions and pending API decisions

- `architecture/configuration.md`:
  - New section **"Password recovery and account edit"**: `PASSWORD_RESET_TOKEN_TTL`
    (`1800`), `PASSWORD_RESET_COOLDOWN` (`60`), `ACCOUNT_EDIT_MAX_ATTEMPTS` (`5`),
    `ACCOUNT_EDIT_LOCK_TTL` (`300`), each linking its endpoint spec.
  - Rules: counts must be positive integers (otherwise boot is refused); the documented
    **exception** for `USER_APPROVAL_REQUIRED`: only the exact value `false` disables
    approval.
  - `USER_APPROVAL_REQUIRED` row: default `true`; effect "approval required unless exactly
    `false`"; rationale (open registration lets strangers make the server open SMTP
    connections to arbitrary hosts — SSRF-style probing).
  - `FRONTEND_BASE_URL` row: empty → system emails are not sent.
  - Trailing sentence → "Settings for device authorization (#29) are added by that spec."
- `api/conventions/validation.md`: new template `<field> must be shorter than or equal to N
  bytes`.
- `api/conventions/errors.md`: check the specific-code registry covers every code used
  (`USERNAME_TAKEN`, `EMAIL_TAKEN`, `USER_DENIED`); no new codes expected. Update the
  enumeration-safety section to name register as the documented exception.
- `api/pending.md`: extend the rate-limit Pending decision to name login, register and
  recover, leaning towards a per-IP throttle (failures per window → `429`, client IP via
  `TRUSTED_PROXY_HOPS`); the recover cooldown is the only v1 protection.
- Deployment note recommending proxy-level rate limiting on `/auth/*.json` (in the
  deployment specs, e.g. `deployment/pipeline.md` notes or a short production note).

## Files to Change
- `docs/agents/specs/architecture/configuration.md`
- `docs/agents/specs/api/conventions/validation.md`
- `docs/agents/specs/api/conventions/errors.md`
- `docs/agents/specs/api/pending.md`
- `docs/agents/specs/deployment/pipeline.md` (or another deployment spec file)
