# Dependencies Policy

Purpose: constrain what libraries the project may depend on.

## Backend (Composer)

- **Runtime (`require`)**: `php` (8.4) and `monolog/monolog` only. Required PHP extensions:
  `pdo_mysql` and `sodium` (plus what the base image provides).
  - `sodium` encrypts the stored SMTP passwords (see
    [email/password-encryption.md](../email/password-encryption.md)). It is declared as
    `"ext-sodium": "*"` in `require` of `source/composer.json` (added by the implementation
    issue), so `composer install` / `composer check-platform-reqs` fail on a PHP without it.
  - The official `php:8.4` images compile `sodium` in; the CircleCI image, which has a
    different base, must be confirmed to have it wherever PHPUnit runs. The production host
    is covered by `bin/check-config` (see [configuration.md](configuration.md#bin-check-config)),
    which runs before every release.
- **Dev (`require-dev`)**: `phpunit/phpunit`, `squizlabs/php_codesniffer`,
  `slevomat/coding-standard`, `phpmd/phpmd` (as used in the tent repository).
- **phpdocumentor** is the documentation tool but is **not** a Composer dependency: every
  `phpdocumentor/phpdocumentor` 3.x release requires `league/flysystem` ^1.0, and Composer
  blocks all flysystem 1.x releases due to security advisories (PKSA-w9tt-7782-78jx,
  PKSA-pwh8-d4fr-nywn). Ignoring advisories is not acceptable.
- **Decision (issue #6):** phpDocumentor runs from a pinned, sha256-verified official phar
  (latest 3.x release at implementation time) baked into the locally built `dev_pidgey` image
  at `/usr/local/bin/phpdoc`. The phar bundles flysystem 1.x internally; this is accepted
  because it is used only for local documentation generation and is never shipped in the
  production (`pidgey`) or CI (`circleci_pidgey-base`) images. It is not installed in
  `dev_pidgey-base`.
- Adding any new runtime dependency requires updating this spec first.

## Frontend (Yarn)

- See [frontend/stack.md](../frontend/stack.md). Frontend dependencies never reach the PHP
  runtime; only the built `dist/` is deployed.

Reference: https://github.com/darthjee/tent `source/composer.json`.
