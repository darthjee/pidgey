# Plan: Configuration: env-file loader and bin/check-config

Issue: [97-configuration-env-file-loader-and-bin-check-config.md](../../issues/97-configuration-env-file-loader-and-bin-check-config.md)

## Overview
Add an env-file loader (`config/.env`, never overriding variables already set) that runs at the start of `public/index.php` and of every `bin/*` script. Add `bin/check-config`, which loads the file and checks every boot-refusing rule in [architecture/configuration.md](../../specs/architecture/configuration.md), plus PHP 8.4.x. It names failed checks and never prints a value. The backend agent writes the code and tests. The product-owner aligns two spec details with the decisions made while refining the issue.

## Agents involved

- [backend](backend.md)
- [product-owner](product-owner.md)

## Shared contracts

- **Env file:** `config/.env` relative to the app root (`source/`). The format and precedence are exactly as in `configuration.md` § *Env file*.
- **`bin/check-config`:**
  - Exit `0` when every check passes, `1` otherwise.
  - It prints one line per failed check on stderr, shaped `<CHECK>: <reason>`: `SECRET_KEY: missing`, `SECRET_KEY: too short`, `PREVIOUS_SECRET_KEYS: empty entry`, `PREVIOUS_SECRET_KEYS: entry too short`, `DB_HOST: missing`, `<NAME>: not a positive integer`, `TRUSTED_PROXY_HOPS: not a non-negative integer`, `ACCESS_TOKEN_TTL: not less than REFRESH_TOKEN_TTL`, `PHP version: not 8.4.x`, `env file: unreadable`.
  - On success it prints one line on stdout: `configuration OK`.
  - It never prints a value.
- **Decisions from the refinement:**
  - `public/index.php` only loads the env file and does not run the validator yet.
  - The PHP version check is unit-tested by passing the version in; there is no environment override.

## CI Checks
- `source`: `composer lint` (CI job: lint)
- `source`: `composer coverage`, after `composer db:prepare` (CI job: tests)
