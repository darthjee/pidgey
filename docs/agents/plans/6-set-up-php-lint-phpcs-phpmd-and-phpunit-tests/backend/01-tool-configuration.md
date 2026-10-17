# Tool configuration

Add the lint and test configuration files under `source/`, following the Tent repository (`darthjee/tent`):

- **`phpcs.xml`:**
  - PSR-12.
  - slevomat sniffs: `SlevomatCodingStandard.Variables.UnusedVariable`, `SlevomatCodingStandard.Variables.UselessVariable`, and `SlevomatCodingStandard.Namespaces.UnusedUses` with `searchAnnotations=true`.
  - `Squiz.Commenting.FunctionComment`, so PHPDoc is required.
  - Exclude `PSR1.Files.SideEffects.FoundWithSymbols` and `Squiz.Commenting.FunctionComment` under `tests/*`.
  - Files: `lib`, `public`, `tests`. Exclude `vendor/*`. Use colors and `-p` style args as in Tent.
  - Make sure the existing `public/index.php` stub passes.
- **`phpmd_unusedcode.xml`:** a ruleset with only `rulesets/unusedcode.xml` (unused private fields, methods, locals, formal parameters). This is the lint gate.
- **`phpmd.xml`:** a fuller ruleset (`codesize`, `design`, `naming`, `unusedcode`, `cleancode`, `controversial`) used only for the `complexity` HTML report.
- **`phpunit.xml`:**
  - Bootstrap `vendor/autoload.php`.
  - Test suites `unit` (`tests/unit`) and `integration` (`tests/integration`).
  - `<source>` includes `lib/`.
  - `cacheDirectory=".phpunit.cache"`.
  - Coverage through the pcov driver, already in the base image.
- **`phpdoc.dist.xml`:** source `lib`, output target `phpdoc`, cache `.phpdoc/cache`, and the title "Pidgey".

## Files to Change
- `source/phpcs.xml` — new.
- `source/phpmd_unusedcode.xml` — new.
- `source/phpmd.xml` — new.
- `source/phpunit.xml` — new.
- `source/phpdoc.dist.xml` — new.
