# Repository, container, fixture and tests

Add the repository, expose it through the container, add the fixture, and cover them with
tests.

## `Pidgey\Repository\SmtpConnectionRepository`

A `final class` built with `__construct(private readonly PDO $pdo)`. Its `COLUMNS` constant is
the one from the repositories.md example.

- `create(int $organizationId, string $name, string $host, int $port, SmtpEncryption $encryption, ?string $username, ?string $encryptedPassword, string $fromAddress): SmtpConnection`
  - `INSERT` lists every column except `id`, `active`, `created_at` and `updated_at`, so the
    defaults apply and the row starts inactive.
  - Bind with `bindValue`, so each value gets an explicit type:
    - `port`: `PDO::PARAM_INT`;
    - `encryption`: `$encryption->value`;
    - `password`: `PDO::PARAM_LOB` when not null, `PDO::PARAM_NULL` when null;
    - `username`: `PDO::PARAM_NULL` when null.
  - Re-read the row through `findInOrganization($organizationId, (int) lastInsertId())`, and
    throw `LogicException` if it cannot be read back, as `ApiTokenRepository::create` does.
  - The doc comment says the password is the ciphertext from the service, stored as opaque
    bytes. It also says the both-or-neither credential rule and activation are callers' jobs.
  - `@throws PDOException`, e.g. for an unknown organization.
- `findInOrganization(int $organizationId, int $id): ?SmtpConnection`: `WHERE organization_id =
  :organization_id AND id = :id`.
- `listByOrganization(int $organizationId): array`, `@return list<SmtpConnection>`:
  `WHERE organization_id = :organization_id ORDER BY id`. Map each row through
  `SmtpConnection::fromRow`.
- Share a private `fetchOne()` helper for the single-row query, as `ApiTokenRepository` does.
- Never put a bound value, especially the password, in an exception message.

## `Container`
- Add `private ?SmtpConnectionRepository $smtpConnectionRepository = null;` and
  `smtpConnectionRepository(): SmtpConnectionRepository` (memoized, built on `$this->pdo()`).
  Copy the `apiTokenRepository()` doc comment and `@throws`.
- `ContainerTest`: add a case like the `apiTokenRepository` one. It checks the instance type,
  that the same instance is returned, and that the shared PDO is used.

## `Fixtures::smtpConnection(...)`

Named overrides, with these defaults:

| Override | Default |
| --- | --- |
| `?int $organizationId` | `$this->organization()` |
| `?string $name` | `$this->unique('smtp')` |
| `string $host` | `'mailpit'` |
| `int $port` | `1025` |
| `string $encryption` | `'none'` |
| `?string $username` | `null` |
| `?string $password` | `null` (raw bytes) |
| `string $fromAddress` | `'noreply@example.com'` |
| `bool $active` | `false` |

- Insert with plain SQL through `insert()`. Make sure binary password bytes survive the
  generic `insert()`. If they don't, bind the password as `PDO::PARAM_LOB` for this fixture.
- Store `active` as `0` / `1`.

## Integration tests: `SmtpConnectionRepositoryTest`

The test extends `RepositoryTestCase`, uses no literal ids, and creates every row through
`Fixtures`.
- **`create` without credentials**: all fields are returned, `username`, `encryptedPassword`
  and `hasPassword()` are empty, `active` is false, and `createdAt` / `updatedAt` are set.
- **`create` with credentials**:
  - use a password made of non-UTF-8 bytes and a NUL, e.g. `"\x00\xff\xfe" . random_bytes(32)`;
  - `encryptedPassword` comes back byte-for-byte identical (`assertSame`);
  - `hasPassword()` is true;
  - a direct `SELECT password` returns the same bytes.
- **New row is inactive**: true even when the organization already has an active connection
  (`Fixtures::smtpConnection(active: true)`), because the repository applies no business rule.
- **Unknown organization**: `create` throws `PDOException` when given an organization id that
  does not exist. Get that id without a literal: create an organization, then use its id plus a
  large offset.
- **`findInOrganization`**:
  - finds a fixture row, with every field mapped, including `encryption` for each enum case and
    `active` true;
  - returns `null` for a missing id;
  - returns `null` for a row of another organization.
- **`listByOrganization`**:
  - returns the organization's rows in ascending id order;
  - excludes another organization's rows;
  - returns `[]` for an organization with none.

## Files to Change
- `source/lib/Repository/SmtpConnectionRepository.php`: new repository.
- `source/lib/Container.php`: property and getter.
- `source/tests/unit/ContainerTest.php`: getter test.
- `source/tests/support/Fixtures.php`: `smtpConnection()`.
- `source/tests/integration/Repository/SmtpConnectionRepositoryTest.php`: new integration test.
