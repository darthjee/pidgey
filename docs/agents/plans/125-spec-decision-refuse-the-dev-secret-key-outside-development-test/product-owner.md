# Product Owner Plan: Spec decision: refuse the dev SECRET_KEY outside development/test

Main plan: [plan.md](plan.md)

## Overview

Record the decided development-key rule in `architecture/configuration.md`. Remove the pending item there and in the authentication index, and add the test cases to `testing/backend.md`. Only spec files change.

## Context

- `SECRET_KEY` signs access-token JWTs. A production instance on the public development key would let anyone forge sessions.
- `configuration.md` claims `.env` "ships a clearly fake development key", but `.env` has no `SECRET_KEY` line today.
- `PHP_ENV` is set only by docker-compose (`development`, `test`) and CI (`test`); production may leave it unset.
- What a refused boot looks like is already defined:
  - `bin/check-config` prints `<CHECK>: <reason>` lines and exits `1`.
  - The web boot logs the error and answers a `500 INTERNAL_ERROR` envelope (`request-handler/front-controller.md#failures-outside-the-wrapper`).

## Decided rule (the contract #128 implements)

- **Development key:** `pidgey-development-only-insecure-secret-key-0000`. It is 48 bytes, contains no `,`, and is recognizable as fake.
- **Recognition:** an exact byte comparison against that value, for `SECRET_KEY` and for each trimmed `PREVIOUS_SECRET_KEYS` entry. There is no prefix or marker matching.
- **When it applies:** unless `PHP_ENV` is exactly `development` or `test`. Every other value counts as production: unset, empty, a different case such as `Production`, or anything else.
- **Order:** a separate check, run after the missing/length checks (the development key passes the length rule).
- **Failure names:** `SECRET_KEY: development key` and `PREVIOUS_SECRET_KEYS: development key`. Each is reported at most once and never with a value.
- **Web boot:** the existing invalid-configuration path (logged, then `500` envelope); nothing new.
- **Implementation (#128):** adds `SECRET_KEY=<development key>` to `.env` and a code constant holding the same value.

## Implementation Steps

### Step 1 — `architecture/configuration.md`

- **Secret keys** section:
  - Replace the bullet claiming `.env` ships a fake key with the decided rule above: the literal key, exact comparison, `PHP_ENV` condition, order and failure names.
  - State that the `.env` line and the code constant land with the access-token work (#128) and must hold the same value.
  - Keep the existing advice: production keys are generated with a CSPRNG and stored only in the production env file.
- **`bin/check-config`** section: add `SECRET_KEY: development key` to the output examples, and mention that `PREVIOUS_SECRET_KEYS: development key` follows the same naming.
- **Settings table:** add to the `PHP_ENV` row that any value other than `development` / `test` (including unset) is treated as production by the development-key rule.
- **Pending decisions:** remove the development-key item. Keep the log-level item.

### Step 2 — `api/authentication.md` and `testing/backend.md`

- `api/authentication.md` → **Pending decisions** summary: remove the "Refusing to boot with the shipped development key outside development/test" bullet.
- `testing/backend.md`:
  - **Configuration validator, unit tests:** add `SECRET_KEY: development key` and `PREVIOUS_SECRET_KEYS: development key` to the check-name examples. Then add an explicit case list:
    - the development key as `SECRET_KEY` under `PHP_ENV=production`, under an unset `PHP_ENV` and under `Production` → `SECRET_KEY: development key`;
    - the development key as one `PREVIOUS_SECRET_KEYS` entry (with surrounding whitespace) under a non-development `PHP_ENV` → `PREVIOUS_SECRET_KEYS: development key`;
    - the development key under `PHP_ENV=development` and `PHP_ENV=test` → passes;
    - a different key of the same length under production → passes.
  - **`bin/check-config`, integration tests:** add "the development key with a non-development `PHP_ENV`" to the invalid-configuration examples.

## Files to Change

- `docs/agents/specs/architecture/configuration.md`: the decided rule, the `PHP_ENV` note, the check-config examples, and removal of the pending item.
- `docs/agents/specs/api/authentication.md`: remove the pending summary bullet.
- `docs/agents/specs/testing/backend.md`: the new validator and integration test cases.

## Notes

- Do not edit `.env` or any code; that belongs to #128.
- Leave `docs/agents/issues/` and `docs/agents/plans/` untouched.
- Final check: `grep -rn "development key" docs/agents/specs` should show only the decided rule and its test cases, with no remaining "Pending decision" wording.
- No CI job lints Markdown; there are no checks to run beyond the grep.
