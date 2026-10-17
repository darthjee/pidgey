# Composer scripts

Add the `scripts` section to `source/composer.json`, exactly as in the plan's shared contracts table:
- `tests`, `tests:unit`, `tests:integration`
- `coverage`
- `lint`, `lint:fix`
- `docs`
- `complexity`

Add `scripts-descriptions` so `composer list` explains each script.

Details:
- `tests` writes HTML coverage to `coverage/` (`--coverage-html coverage`), and `coverage` writes `coverage/clover.xml`. Set `XDEBUG_MODE` only if needed; pcov is the driver.
- `lint` runs `phpcs` first, then the phpmd unused-code gate (`phpmd lib,public,tests text phpmd_unusedcode.xml`). It fails if either fails.
- `lint:fix`: `phpcbf` exits 1 when it fixed files, which must not fail the script. Tolerate exit 1 and fail on exit 2 or higher. Then run the same phpmd gate.
- `docs` runs `phpdoc` (picks up `phpdoc.dist.xml`).
- `complexity` runs `phpmd lib html phpmd.xml --reportfile complexity.html` and must not fail on violations, because it is informational.
- Set `"process-timeout": 0` under `config` if long runs time out.

Regenerate `composer.lock` only if dependencies change; none should.

## Files to Change
- `source/composer.json` — add `scripts` and `scripts-descriptions` (and `config.process-timeout` if needed).
