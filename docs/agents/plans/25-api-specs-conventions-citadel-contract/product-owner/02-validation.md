# Validation rules
Create `api/conventions/validation.md` from Citadel 2.2:

- Validation runs on the body before any business logic or side effect.
- Unknown fields are silently dropped, not rejected.
- All field errors are collected and reported at once.
- The message templates table (`<field> must be a string`, `should not be empty`,
  `must be an email`, min/max length, one of a set).
- Guards run before validation (an anonymous request with an invalid body gets `401`).
- Validation failures answer `400 VALIDATION_FAILED` (envelope in `errors.md`), replacing `422`.

## Files to Change
- `docs/agents/specs/api/conventions/validation.md` — new.
