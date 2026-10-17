# Product-owner Plan: Set up PHP lint (phpcs/phpmd) and PHPUnit tests

Main plan: [plan.md](plan.md)

## Shared contracts

- **What you can rely on:**
  - The phpdoc phar at `/usr/local/bin/phpdoc` in `dev_pidgey` (not in `dev_pidgey-base`, not in the CI image).
  - The composer scripts table in [plan.md](plan.md#shared-contracts).
  - `make tests` = lint + tests in the `tests` container.
  - Coverage is report-only, with no threshold.

## Implementation Steps

### Step 1 — Record the decisions in the specs

- **`architecture/dependencies.md`:** replace "decided in issue #6" with the decision. phpDocumentor runs from a pinned, checksum-verified phar baked into the locally built `dev_pidgey` image. The phar bundles flysystem 1.x internally, which is accepted for local documentation generation only and never shipped in production or CI images.
- **`docker/images.md`:** add the phpdoc phar to the `dev_pidgey` row's content.
- **`testing/backend.md`:**
  - Update the `docs` row (`phpdoc` from the `dev_pidgey` image; output `source/phpdoc/`).
  - Note that `lint:fix` also re-runs the phpmd gate.
  - Note that coverage is report-only (HTML in `coverage/`, Clover in `coverage/clover.xml`) with no enforced threshold yet.
- **`docker/makefile.md`:** state that `tests` currently runs backend lint and tests (`composer lint && composer tests`), and that frontend joins when it exists.
- **`architecture/repository-layout.md`:** add `README.md` (human-facing setup and commands) to the top-level table.

## Files to Change
- `docs/agents/specs/architecture/dependencies.md` — record the phpDocumentor decision.
- `docs/agents/specs/docker/images.md` — `dev_pidgey` content.
- `docs/agents/specs/testing/backend.md` — `docs`/`lint:fix` rows, coverage note.
- `docs/agents/specs/docker/makefile.md` — `tests` row.
- `docs/agents/specs/architecture/repository-layout.md` — `README.md` row.
