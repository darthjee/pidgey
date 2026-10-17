# Backend Plan: Set up PHP lint (phpcs/phpmd) and PHPUnit tests

Main plan: [plan.md](plan.md)

## Shared contracts

- **What you produce:** the composer scripts table in [plan.md](plan.md#shared-contracts), with these exact names: `tests`, `tests:unit`, `tests:integration`, `coverage`, `lint`, `lint:fix`, `docs`, `complexity`.
- **What you can rely on:**
  - infra provides `/usr/local/bin/phpdoc` in the `dev_pidgey` image.
  - infra git-ignores `coverage/`, `complexity.html`, `.phpunit.result.cache`, `.phpunit.cache/`, `phpdoc/` and `.phpdoc/` under `source/`.
- **`Pidgey\Infra\Env` API:**
  - `Env::get(string $name, ?string $default = null): ?string`
  - `Env::required(string $name): string`, which throws `\RuntimeException` when the variable is unset or empty.

## Steps

- [01 — Tool configuration](backend/01-tool-configuration.md)
- [02 — Composer scripts](backend/02-composer-scripts.md)
- [03 — Test layout and Env class](backend/03-test-layout-and-env.md)

## Notes

- Run everything in the container: `docker compose run --rm tests composer <script>`. If `vendor` is stale, run `composer install` first.
- If phpmd 2.15 prints PHP 8.4 deprecation notices, `lint` must still fail only on rule violations. Exit code 2 means violations; check whether the notices affect the exit code.
