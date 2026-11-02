# Issue: Spec decision: refuse the dev SECRET_KEY outside development/test

## Description

Part of epic #120. Replace the pending decision in `specs/architecture/configuration.md` ("refuse to boot with the shipped development key outside development/test") with a decided rule. The issue is about the spec only; no code changes.

## Problem

- `SECRET_KEY` signs the access-token JWTs. This epic makes login real, so a production instance running on the development key would let anyone forge sessions.
- The spec says `.env` "ships a clearly fake development key", but `.env` has no `SECRET_KEY` line today, and no value is defined that the app could recognize.
- `PHP_ENV` is set only by docker-compose (`development`, `test`) and CI (`test`). On the production host it may be unset.

## Expected Behavior

- Boot is refused when `SECRET_KEY`, or any trimmed `PREVIOUS_SECRET_KEYS` entry, equals the shipped development key, unless `PHP_ENV` is exactly `development` or `test`.
- Any other `PHP_ENV` value, including unset, empty or a different case (`Production`), counts as production, so a missing variable fails safe. A production host with a real key is unaffected.
- **`bin/check-config`** reports `SECRET_KEY: development key` and/or `PREVIOUS_SECRET_KEYS: development key` and exits `1`. It never prints a value.
- **Web boot:** the existing invalid-configuration behavior applies (logged, then `500 INTERNAL_ERROR` envelope, `request-handler/front-controller.md`).

## Solution

Owner: `product-owner` (spec files only).

### `specs/architecture/configuration.md`

- **Secret keys** section:
  - State the exact development key literally: `pidgey-development-only-insecure-secret-key-0000`. It is at least 32 bytes, contains no `,`, and is recognizable as fake.
  - Recognition is an **exact byte comparison** against that value, for `SECRET_KEY` and for each trimmed `PREVIOUS_SECRET_KEYS` entry. There is no prefix or marker matching.
  - It is a separate check that runs after the length check (the development key passes the length rule).
  - The rule applies unless `PHP_ENV` is exactly `development` or `test`.
  - Fix the wording that claims `.env` already ships the key. It is added to `.env` by the access-token issue (#128), together with the code constant that holds the same value.
- **`bin/check-config`** section: add the two failure names to the output examples.
- **Pending decisions:** remove the development-key item.

### Other specs

- `specs/api/authentication.md`: remove the "Refusing to boot with the shipped development key" pending item.
- `specs/testing/backend.md`: add the cases to the configuration validator and `bin/check-config` lists:
  - the development key as `SECRET_KEY`, and as a `PREVIOUS_SECRET_KEYS` entry, under a non-development `PHP_ENV` (including unset) → failure;
  - the same key under `development` / `test` → passes.

### Out of scope

- The implementation: the validator check, the constant and the `.env` line all land in #128.

## Benefits

- A production instance can never sign or accept tokens with the public development key.
- Fail-safe: forgetting `PHP_ENV` in production cannot disable the check.
- No new failure path: it reuses the existing `bin/check-config` and `500` boot-refusal behavior.
