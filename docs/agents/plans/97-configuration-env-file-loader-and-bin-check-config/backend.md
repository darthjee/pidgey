# Backend Plan: Configuration: env-file loader and bin/check-config

Main plan: [plan.md](plan.md)

## Shared contracts

- `config/.env` relative to the app root (`source/`). The format and precedence are exactly as in [configuration.md § Env file](../../specs/architecture/configuration.md#env-file).
- `bin/check-config`:
  - It exits `0` with `configuration OK` on stdout, or `1` with one `<CHECK>: <reason>` line per failure on stderr. See [plan.md](plan.md#shared-contracts) for the exact strings.
  - It never prints a value.
- `public/index.php` loads the env file only; it does not validate.
- The PHP version is passed into the command (default `PHP_VERSION`); there is no environment override.

## Steps

- [01 — Env-file loader](backend/01-env-file-loader.md)
- [02 — Configuration validator](backend/02-configuration-validator.md)
- [03 — bin/check-config command](backend/03-check-config-command.md)

## CI Checks
- `source`: `composer lint` (CI job: lint)
- `source`: `composer coverage`, after `composer db:prepare` (CI job: tests)

## Notes
- No new dependency: the parser is hand-written. Don't add `vlucas/phpdotenv`; its semantics differ (expansion, `export`).
- Error messages must never interpolate values. `Env::integer()` currently puts the value in its exception message, so don't reuse it for validation output.
- Keep the loader free of logging and output.
