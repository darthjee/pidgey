# Dependencies Policy

Purpose: constrain what libraries the project may depend on.

## Backend (Composer)

- **Runtime (`require`)**: `php` (8.x) and `monolog/monolog` only. Required PHP extensions:
  `pdo_mysql` (plus what the base image provides).
- **Dev (`require-dev`)**: `phpunit/phpunit`, `squizlabs/php_codesniffer`,
  `slevomat/coding-standard`, `phpmd/phpmd`, `phpdocumentor` (as used in the tent repository).
- Adding any new runtime dependency requires updating this spec first.

## Frontend (Yarn)

- See [frontend/stack.md](../frontend/stack.md). Frontend dependencies never reach the PHP
  runtime; only the built `dist/` is deployed.

Reference: https://github.com/darthjee/tent `source/composer.json`.
