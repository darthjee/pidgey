# Migration file discovery and validation

Pure, filesystem-only logic that turns a migrations directory into a validated, ordered list —
no database involved, fully unit-testable.

- `Pidgey\Infra\Migrations\Migration` (final, readonly): `version` (int), `name` (the
  description part), `path`, `sql` (the single statement, trimmed), `checksum` (SHA-256 hex of
  the raw file contents).
- `Pidgey\Infra\Migrations\StatementSplitter`: splits SQL text into statements on `;`, ignoring
  semicolons inside single/double/backtick quotes (with backslash and doubled-quote escapes) and
  inside `-- `, `#` and `/* */` comments; returns non-empty statements with comments-only /
  whitespace-only chunks dropped. A trailing `;` is optional.
- `Pidgey\Infra\Migrations\MigrationLoader::load(string $directory): list<Migration>`:
  - Missing directory → error. Empty directory → empty list.
  - Considers only `*.sql` files; non-`.sql` files are ignored.
  - Each `.sql` file must match `^(\d{4})_([a-z0-9_]+)\.sql$` → otherwise error naming the file.
  - Duplicate versions → error listing all conflicting files.
  - Zero statements (empty, whitespace- or comments-only) → error naming the file.
  - More than one statement → error naming the file.
  - Returns migrations sorted by version. Gaps are allowed.
- `Pidgey\Infra\Migrations\MigrationException` (extends `RuntimeException`) for all
  validation/run failures, carrying the offending file path(s) in its message.
- Unit tests using fixture directories under `source/tests/fixtures/migrations/<case>/`
  (create the cases: `valid`, `gaps`, `duplicate_versions`, `bad_name`, `empty_file`,
  `comments_only`, `multi_statement`, `with_readme` (non-`.sql` ignored), `empty_dir`
  (`.gitkeep` only), plus string-based tests for the splitter (quotes, escapes, comments,
  trailing `;`).

## Files to Change
- `source/lib/Infra/Migrations/Migration.php` — new.
- `source/lib/Infra/Migrations/StatementSplitter.php` — new.
- `source/lib/Infra/Migrations/MigrationLoader.php` — new.
- `source/lib/Infra/Migrations/MigrationException.php` — new.
- `source/tests/unit/Infra/Migrations/StatementSplitterTest.php` — new.
- `source/tests/unit/Infra/Migrations/MigrationLoaderTest.php` — new.
- `source/tests/fixtures/migrations/<case>/*.sql` — new fixture sets (shared with step 06).
- `source/migrations/.gitkeep` — new (empty real migrations directory).
