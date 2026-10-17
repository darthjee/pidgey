# Dependencies Policy

Purpose: constrain what libraries the project may depend on.

## Backend (Composer)

- **Runtime (`require`)**: `php` (8.4) and `monolog/monolog` only. Required PHP extensions:
  `pdo_mysql` (plus what the base image provides).
- **Dev (`require-dev`)**: `phpunit/phpunit`, `squizlabs/php_codesniffer`,
  `slevomat/coding-standard`, `phpmd/phpmd` (as used in the tent repository).
- **phpdocumentor** is the documentation tool but is **not** a Composer dependency: every
  `phpdocumentor/phpdocumentor` 3.x release requires `league/flysystem` ^1.0, and Composer
  blocks all flysystem 1.x releases due to security advisories (PKSA-w9tt-7782-78jx,
  PKSA-pwh8-d4fr-nywn). Ignoring advisories is not acceptable. How it runs (official phar or
  the `phpdoc/phpdoc` Docker image) is decided with the `docs` script in issue #6.
- Adding any new runtime dependency requires updating this spec first.

## Frontend (Yarn)

- See [frontend/stack.md](../frontend/stack.md). Frontend dependencies never reach the PHP
  runtime; only the built `dist/` is deployed.

Reference: https://github.com/darthjee/tent `source/composer.json`.
