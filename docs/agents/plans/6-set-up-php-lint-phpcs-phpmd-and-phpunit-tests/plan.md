# Plan: Set up PHP lint (phpcs/phpmd) and PHPUnit tests

Issue: [6-set-up-php-lint-phpcs-phpmd-and-phpunit-tests.md](../../issues/6-set-up-php-lint-phpcs-phpmd-and-phpunit-tests.md)

## Overview

Add the backend quality setup on top of the #5 Docker environment:
- configuration for phpcs, phpmd and PHPUnit;
- the composer scripts listed in `docs/agents/specs/testing/backend.md`;
- the `source/tests/` layout, plus a small real class (`Pidgey\Infra\Env`) with a unit test.

Infra adds the phpDocumentor phar to the locally built `dev_pidgey` image, the `make tests` target and the git-ignore entries. The product owner records the phpDocumentor decision in the specs. The architect writes a root `README.md` with the local commands.

## Agents involved

- [backend](backend.md)
- [infra](infra.md)
- [product-owner](product-owner.md)

Architect-owned work (no separate file):
- **Root `README.md`**: a short project intro and first-run setup that links to `docs/agents/specs/docker/makefile.md`. It lists the local commands from "Shared contracts" below:
  - `make tests`
  - `docker compose run --rm tests composer tests`
  - `composer tests:unit`
  - a single file (`composer tests -- tests/unit/Infra/EnvTest.php`) and `--filter`
  - `lint`, `lint:fix`, `complexity` and `docs`
- **Agent docs**: list `README.md` as an architect-owned root file in `AGENTS.md` / agent docs, if they enumerate root files.

## Shared contracts

### Composer scripts (`source/composer.json`, run from `/home/app/app` in the `tests` container)

| Script | Command (intent) |
| --- | --- |
| `tests` | `phpunit` running all suites, with HTML coverage to `coverage/` |
| `tests:unit` | `phpunit --testsuite unit` |
| `tests:integration` | `phpunit --testsuite integration` |
| `coverage` | `phpunit --coverage-clover coverage/clover.xml` |
| `lint` | `phpcs`, then `phpmd lib,public,tests text phpmd_unusedcode.xml` |
| `lint:fix` | `phpcbf` (a successful fix must not fail the script), then the same phpmd gate |
| `docs` | `phpdoc` (config in `phpdoc.dist.xml`; output `phpdoc/`, cache `.phpdoc/`) |
| `complexity` | `phpmd lib html phpmd.xml --reportfile complexity.html` (informational, not a gate) |

Extra arguments pass through: `composer tests -- --filter EnvTest`.

### phpDocumentor binary

- The `dev_pidgey` image provides an executable `/usr/local/bin/phpdoc`. It is a pinned, sha256-verified phar of the latest phpDocumentor 3.x release, chosen at implementation time.
- It is not a Composer dependency. The CI image does not get it.

### Generated files (git-ignored, all under `source/`)

- `coverage/`
- `complexity.html`
- `.phpunit.result.cache` and `.phpunit.cache/`
- `phpdoc/` and `.phpdoc/`

### Make target

`make tests` runs `docker compose run --rm tests sh -c "composer lint && composer tests"`.

### `Pidgey\Infra\Env` (for #7)

- `Env::get(string $name, ?string $default = null): ?string` returns the variable's value, or `$default` when it is unset or empty.
- `Env::required(string $name): string` returns the value, or throws `\RuntimeException` naming the variable when it is unset or empty.
