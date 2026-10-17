# Backend Plan: Set up Docker dev environment (images, Makefile, docker-compose)

Main plan: [plan.md](plan.md)

## Shared contracts

- `composer.json` / `composer.lock` live in `source/`; the images `COPY composer.*` from the
  `source/` build context and run `composer install` there.
- Container app root `/home/app/app` = `./source`; web root `source/public/`.
- Stub response: `GET /` → `200`, `Content-Type: application/json`, `{"status":"ok"}`.

## Implementation Steps

### Step 1 — Minimal composer.json and index.php stub
- Create `source/composer.json`:
  - `name`: `darthjee/pidgey`; PSR-4 `"Pidgey\\": "lib/"`.
  - `require`: `php` `^8.4`, `monolog/monolog`.
  - `require-dev`: `phpunit/phpunit`, `squizlabs/php_codesniffer`,
    `slevomat/coding-standard`, `phpmd/phpmd`, `phpdocumentor/phpdocumentor`.
  - `config.allow-plugins`: `dealerdirect/phpcodesniffer-composer-installer: true`.
  - No `scripts` yet (#6).
  - Follow `docs/agents/specs/architecture/dependencies.md`. Add `source/lib/.gitkeep` so the
    PSR-4 root exists.
- Create `source/public/index.php`: a throwaway stub that sends
  `Content-Type: application/json` and echoes `{"status":"ok"}`. No routing, no
  `.htaccess`, no tests. A short comment marks it as a placeholder until the front controller
  is built.

### Step 2 — Generate and commit composer.lock (after infra steps 01–03)
Once the images build, run `docker compose run --rm tests composer install`. It writes
`source/composer.lock` through the `./source` mount. Commit the lock. Then rebuild with
`make build`, so `dev_pidgey` installs from the lock. If version resolution fails (for
example phpdocumentor against phpunit), pick compatible constraints and note the choice in the
PR.

## Files to Change
- `source/composer.json` — new; minimal dependencies.
- `source/composer.lock` — new; generated inside the container.
- `source/lib/.gitkeep` — new; PSR-4 root placeholder.
- `source/public/index.php` — new; throwaway JSON stub.

## Notes
- Composer never runs on the host: always run it through the `tests` container.
