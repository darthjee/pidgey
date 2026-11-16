# Add the Schema base test case and rewrite SchemaTest by aspect

Create `abstract class SchemaTestCase extends IntegrationTestCase` in `source/tests/support/SchemaTestCase.php` holding every constant of `SchemaTest` (as `protected const`) and the `information_schema` helpers `columns`, `uniqueKeys`, `indexes`, `foreignKeys`, `collation`, `indexColumns` (as `protected`), verbatim.

Create these classes in `source/tests/integration/Schema/` (each `final`, extending `SchemaTestCase`, with `#[CoversNothing]`), then delete `SchemaTest.php`. Each provider is `public static`, keyed by table name, one row per table, with the expected value copied unchanged from the matching original test:

| Class | Tests and providers |
|---|---|
| `SchemaTablesTest` | testTableSet (verbatim); collationProvider (9 tables, each `self::COLLATION`) + testCollation(string $table, string $expected) |
| `SchemaColumnsTest` | columnsProvider (9 tables) + testColumns(string $table, array $expected); testUsersStatusHasNoDefault and testSmtpConnectionsActiveOrganizationIdIsGeneratedFromActive (verbatim) |
| `SchemaKeysTest` | uniqueKeysProvider + testUniqueKeys, indexesProvider + testIndexes, foreignKeysProvider + testForeignKeys (9 tables each; organizations rows are `[]`, from `testOrganizationsKeys`) |

Test cases: 1 + 9 + 9 + 2 + 27 = 48 (was 46: `testOrganizationsKeys` becomes 3 rows). Every expected array of the original survives in exactly one provider row.

## Files to Change
- `source/tests/support/SchemaTestCase.php` — new.
- `source/tests/integration/Schema/Schema{Tables,Columns,Keys}Test.php` — new.
- `source/tests/integration/Schema/SchemaTest.php` — deleted.
