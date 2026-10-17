# Issue: Set up PHP lint (phpcs/phpmd) and PHPUnit tests

## Description

Part of the backend bootstrap Epic (#3). Configure linting and testing for the PHP backend, all run inside Docker through composer scripts. Follows `docs/agents/specs/testing/backend.md` and the Tent repository (`darthjee/tent`) configuration.

Depends on #5 (merged): `dev_pidgey` images, the compose `tests` service and the minimal `source/composer.json` (phpunit, phpcs, slevomat and phpmd already required).

## Problem

`source/composer.json` declares the quality tools but there is no configuration, no composer scripts, no test layout and no `make tests` target. The definition of done ("`make tests` is green") cannot be checked yet, and CI (#8) has nothing to run. #5 also deferred how phpDocumentor runs (it cannot be a Composer dependency, see `docs/agents/specs/architecture/dependencies.md`).

## Expected Behavior

- `docker compose run --rm tests composer lint` passes on the repository.
- `docker compose run --rm tests composer tests` passes and writes `coverage/index.html`.
- `docker compose run --rm tests composer coverage` writes `coverage/clover.xml`.
- Introducing an unused `use` or a missing PHPDoc in `lib/` makes `composer lint` fail.
- `make tests` runs lint and tests in the `tests` container and fails if either fails.

## Solution

- **`source/phpcs.xml`**: PSR-12; slevomat `UnusedVariable`, `UselessVariable`, `UnusedUses` (with `searchAnnotations`); `PSR1.Files.SideEffects.FoundWithSymbols` and `Squiz.Commenting.FunctionComment` (PHPDoc required) excluded under `tests/*`; files `lib`, `public`, `tests`; exclude `vendor/*`.
- **`source/phpmd_unusedcode.xml`**: unused private fields/methods, used as a lint gate.
- **`source/phpmd.xml`**: fuller complexity ruleset for the HTML report.
- **`source/phpunit.xml`**: bootstrap `vendor/autoload.php`; suites `unit` (`tests/unit`) and `integration` (`tests/integration`); coverage includes `lib/` (pcov driver).
- **Composer scripts** in `source/composer.json`:
  - `tests` (all suites, HTML coverage to `coverage/`), `tests:unit`, `tests:integration`
  - `coverage` (clover to `coverage/clover.xml`)
  - `lint` (phpcs + phpmd unused-code), `lint:fix` (phpcbf + phpmd unused-code)
  - `docs` (`phpdoc`, output to a git-ignored folder), `complexity` (phpmd HTML report to `complexity.html`)
- **Test layout**: `source/tests/unit/` (mirrors `lib/`, one `*Test.php` per class), `source/tests/integration/`, `source/tests/support/` (helpers + loader), `source/tests/fixtures/`.
- **phpDocumentor**: `dockerfiles/dev_pidgey/Dockerfile` downloads a pinned, checksum-verified phpDocumentor phar to `/usr/local/bin/phpdoc`. Only the locally built `dev_pidgey` image changes; `dev_pidgey-base` is not rebuilt or pushed, and the CI image does not need it. Update `docs/agents/specs/architecture/dependencies.md`, `docker/images.md` and `testing/backend.md` to record this. The phar bundles flysystem 1.x internally; that is accepted for local documentation generation only.
- **Sample code**: `Pidgey\Infra\Env` in `source/lib/Infra/Env.php`, with PHPDoc, reads an environment variable with an optional default and throws when a required variable is missing. A matching `tests/unit/Infra/EnvTest.php` covers it. #7 can reuse it for the DB config.
- **Makefile**: `tests` target running lint + tests in the `tests` container.
- **Coverage**: reports only (HTML + clover); no minimum threshold is enforced. A gate can be decided later with #8/Codacy.
- **Docs**: create a root `README.md` (owned by `architect`; add it to `docs/agents/specs/architecture/repository-layout.md`). It covers first-run setup (pointing to `docker/makefile.md`) and the local commands: `make tests`, `docker compose run --rm tests composer tests`, `tests:unit`, a single file / `--filter`, `lint`, `lint:fix`, `complexity` and `docs`.

### Edge cases

- `tests/integration/` holds only a placeholder until #7; `composer tests` / `tests:integration` must still succeed with an empty integration suite.
- Generated output (`coverage/`, `complexity.html`, `.phpunit.result.cache`, docs output) is git-ignored.
- phpmd 2.15 may print PHP 8.4 deprecation notices; `lint` must fail only on rule violations, not on notices.

### Out of scope

- Database/integration tests against MySQL or Mailpit (#7).
- CircleCI (#8).
- Frontend tests/lint (`make tests` covers backend only until the frontend exists).

## Benefits

- The definition of done becomes checkable locally with `make tests`.
- #7 and #8 can build on a working test and lint setup.
