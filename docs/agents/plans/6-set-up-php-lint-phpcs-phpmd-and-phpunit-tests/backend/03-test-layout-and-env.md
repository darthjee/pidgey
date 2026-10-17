# Test layout and Env class

Create the test layout and the first real class.

**Test layout:**
- `tests/unit/` mirrors `lib/`, with one `*Test.php` per class.
- `tests/integration/` holds only `.gitkeep` until #7.
- `tests/support/` holds a `loader.php` (or equivalent) that requires shared helpers. Wire it into PHPUnit (bootstrap or autoload-dev) only if it has content; otherwise keep a `.gitkeep`.
- `tests/fixtures/` holds `.gitkeep`.

If test helpers need autoloading, add `autoload-dev` for `Pidgey\Tests\` → `tests/` in `composer.json`.

**`Pidgey\Infra\Env`** (`lib/Infra/Env.php`), with full PHPDoc and `declare(strict_types=1)`:
- `public static function get(string $name, ?string $default = null): ?string` returns `getenv($name)`, or `$default` when it is `false` or `''`.
- `public static function required(string $name): string` returns the value, or throws `\RuntimeException("Missing required environment variable: <name>")` when it is unset or empty.

**`tests/unit/Infra/EnvTest.php`** covers:
- set value
- unset with and without a default
- empty treated as unset
- required present
- required missing (throws)

Use `putenv()` in setUp/tearDown to isolate the variables. Use the `#[CoversClass(Env::class)]` attribute.

**Empty integration suite:** `composer tests` and `composer tests:integration` must exit 0 while the integration suite is empty. PHPUnit 12 reports "No tests executed" but exits 0 unless `failOnEmptyTestSuite` is set. Verify this. If it fails, adjust `phpunit.xml`, but do not add a fake test.

**Acceptance:** verify these against the issue's acceptance criteria:
- `composer lint` passes.
- `composer tests` writes `coverage/index.html`.
- `composer coverage` writes `coverage/clover.xml`.
- Temporarily adding an unused `use` or removing a PHPDoc block in `lib/` makes `composer lint` fail. Revert the change afterwards.

## Files to Change
- `source/lib/Infra/Env.php` — new.
- `source/tests/unit/Infra/EnvTest.php` — new.
- `source/tests/integration/.gitkeep`, `source/tests/fixtures/.gitkeep`, `source/tests/support/` (loader or `.gitkeep`) — new.
- `source/composer.json` — `autoload-dev`, only if needed.
