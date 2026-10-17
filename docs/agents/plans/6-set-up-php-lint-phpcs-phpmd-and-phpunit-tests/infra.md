# Infra Plan: Set up PHP lint (phpcs/phpmd) and PHPUnit tests

Main plan: [plan.md](plan.md)

## Shared contracts

- **What you produce:**
  - `/usr/local/bin/phpdoc` (an executable, pinned and sha256-verified phpDocumentor 3.x phar) in the `dev_pidgey` image.
  - The `make tests` target: `docker compose run --rm tests sh -c "composer lint && composer tests"`.
  - Git-ignore entries for `source/coverage`, `source/complexity.html`, `source/.phpunit.result.cache`, `source/.phpunit.cache`, `source/phpdoc` and `source/.phpdoc`.
- **What you can rely on:** backend provides the composer scripts `lint` and `tests`.

## Implementation Steps

### Step 1 — phpDocumentor phar in `dev_pidgey`

In `dockerfiles/dev_pidgey/Dockerfile`, before the composer install:
1. Switch to `USER root`.
2. `curl` the phpDocumentor phar for a pinned version from its GitHub release (`ARG PHPDOC_VERSION`), and check it against a pinned sha256 (`ARG PHPDOC_SHA256`, with `sha256sum -c`).
3. Install it as `/usr/local/bin/phpdoc` with mode 0755.
4. Switch back to `USER app`.

Use the latest 3.x release available at implementation time, and confirm it runs on PHP 8.4 (`phpdoc --version`). Do not touch `dev_pidgey-base`: it is not rebuilt or pushed, and `BASE_VERSION` stays `0.0.1`. Mirror the build args in `docker-compose.yml`'s `base_build` service only if defaults are not baked into the Dockerfile; prefer baked-in defaults.

### Step 2 — `make tests` and git-ignore

- Add a `tests` target (and add it to `.PHONY`) to the `Makefile`, running `docker compose run --rm tests sh -c "composer lint && composer tests"`. It must fail if either command fails.
- Add the missing generated paths to `.gitignore`: `source/.phpunit.cache`, `source/phpdoc`, `source/.phpdoc`. `coverage`, `.phpunit.result.cache` and `complexity.html` are already there.

## Files to Change
- `dockerfiles/dev_pidgey/Dockerfile` — install the phpdoc phar.
- `Makefile` — `tests` target.
- `.gitignore` — new generated paths.

## Notes
- Verify with `make build`, then `docker compose run --rm tests phpdoc --version`. Once backend's scripts exist, also verify `make tests`.
- `docs/agents/specs/docker/makefile.md` already lists `tests`; product-owner adjusts its wording (backend only for now).
