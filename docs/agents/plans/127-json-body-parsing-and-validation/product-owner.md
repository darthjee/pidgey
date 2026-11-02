# Product Owner Plan: JSON body parsing and validation

Main plan: [plan.md](plan.md)

## Shared contracts

The backend implements exactly what these specs say; see the `Validator` contract in [plan.md](plan.md#shared-contracts) for the decided rules to record.

## Implementation Steps

### Step 1 — Record the validation decisions in `api/conventions/validation.md`
Add a **Field evaluation** section with the rules decided on the issue:
- absent or `null` → `<field> should not be empty`; non-string for a string field → `<field> must be a string`; in both cases no further rule runs for that field (one message per field);
- a field that passes gets every failed rule, in declaration order; fields are reported in declaration order;
- "not empty" is checked after trimming (cross-link `endpoints/auth.md#trimming`);
- **email** means PHP's `FILTER_VALIDATE_EMAIL` on the trimmed value, no IDN/Unicode local part in v1, length checked separately;
- **lengths**: "N characters" counts UTF-8 code points, "N bytes" counts bytes; invalid UTF-8 never reaches validation (the JSON parser rejects it with `400`).
Keep the existing pending decision for other rule templates.

### Step 2 — Record the `Validator` shape in `request-handler/handlers.md#body-validation`
Replace "The `Validator` API and its rule set are left to the implementation issue" with the decided fluent shape (`new Validator($request->json())`, `string(field)` + `trim/notEmpty/email/minLength/maxLength/maxBytes/noNulByte`, `validated()` returning only declared, trimmed fields or throwing `ValidationFailedException`). Note that "one of a set" and integer rules are added when an endpoint first needs them. `request-response/request.md` already specifies the body parser and the NUL path rule, so it needs no change.

## Files to Change
- `docs/agents/specs/api/conventions/validation.md` — new Field evaluation section.
- `docs/agents/specs/request-handler/handlers.md` — `Validator` API in Body validation.

## Notes
- Docs only; no code. Do it first so the backend step follows the written spec.
