# Product-owner Plan: Configuration: env-file loader and bin/check-config

Main plan: [plan.md](plan.md)

## Shared contracts

- The PHP version check is unit-tested by passing the version in; there is no environment override.
- For now, `public/index.php` loads the env file but does not run the validator.

## Implementation Steps

### Step 1 — Align the specs with the refinement decisions
- `testing/backend.md` § *Configuration loading*:
  - Move the "PHP version other than 8.4.x" case from the process tests to a new unit-test bullet for the command and validator.
  - The unit test passes the version in (e.g. `8.3.0`, `8.5.0`), and the command defaults to `PHP_VERSION`.
  - Add a unit-test bullet for the validator, covering each rule.
- `architecture/configuration.md`:
  - Under *Rules* ("refuses to start"), or under *`bin/check-config`*, note that the web boot refusal isn't wired yet.
  - Until then, `bin/check-config`, run before every release, enforces these rules. The web refusal lands with the auth work that first reads `SECRET_KEY`.
  - Record this as a **Pending decision** if that fits the page's pattern better.

## Files to Change
- `docs/agents/specs/testing/backend.md`: the PHP version test moves to the unit tests, and validator unit tests are added.
- `docs/agents/specs/architecture/configuration.md`: a note that the web boot refusal is not wired yet.

## Notes
- Keep the *Purpose:* line and the *Pending decisions* section of both files.
