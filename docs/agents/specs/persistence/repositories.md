# Repositories

Purpose: define the `Pidgey\Repository\` classes, the only code that writes SQL for the application tables.

## Shape

- One repository per table, named `<Entity>Repository` (`UserRepository`,
  `SmtpConnectionRepository`, …).
- `final class`, constructed with the request's PDO only:
  `public function __construct(private readonly PDO $pdo)`.
- Exposed through the container as typed, memoized getters, e.g.
  `Container::userRepository(): UserRepository`, each built on `Container::pdo()`
  ([connection.md](connection.md#one-connection-per-request)). Each table's implementation
  adds its getter.
- Repositories return entities ([entities.md](entities.md)), scalars or nothing. They never
  return raw rows or `PDOStatement`s.
- Repositories hold no business rules (status transitions, lockout thresholds, which
  connection is active): those belong to services and handlers. A repository runs the
  statement it is asked to run.
- Repositories never begin, commit or roll back a transaction
  ([connection.md](connection.md#transactions)).
- Repositories never throw HTTP exceptions. `PDOException` propagates unchanged; the caller
  decides what it means (e.g. a duplicate-key error on `users.username`).

## SQL rules

- Prepared statements with **named** placeholders for every value; no value is ever
  interpolated into SQL.
- Identifiers (table and column names) are literals in the repository source, never built
  from input.
- One statement per `prepare()`.

## Column lists

- `SELECT *` is not used. Each repository declares one private constant with its column list
  (`private const COLUMNS = 'id, organization_id, username, …'`) and every `SELECT` that
  builds an entity uses it, so `fromRow()` always receives every column it needs.

## Method naming

| Kind | Name | Returns |
| --- | --- | --- |
| Single row by key | `findById(int $id)`, `findByTokenHash(string $hash)`, `findByUsername(string $username)` | `?Entity` (`null` when not found) |
| Organization-scoped single row | `findInOrganization(int $organizationId, int $id)` | `?Entity` |
| Several rows | `listByOrganization(int $organizationId)`, `listBy…(…)` | `list<Entity>`, in a documented, deterministic order (default `ORDER BY id`) |
| Insert | `create(…)` | the created `Entity`, re-read by id inside the same connection, so column defaults (`created_at`, `active = false`, …) are reflected |
| Update | verb named after the change: `updateStatus(…)`, `revoke(…)`, `markUsed(…)`, `touchLastUsed(…)` | `bool`: `true` when a row matched |
| Lock | `lockById(int $id)`, `lockByUuid(string $uuid)` | `?Entity`, read with `FOR UPDATE` |
| Delete | `deleteInOrganization(int $organizationId, int $id)` | `bool`: `true` when a row was deleted |

- `create()` takes explicit typed arguments (or a small readonly input object when there are
  many), never an entity: there is no id or `created_at` yet.
- Updates and deletes affect one row, identified by its key; a method that touches several
  rows says so in its name (e.g. `revokeAllForUser()`, `deactivateAllInOrganization()`).
- Arguments that hold enums take the enum, not its string value.

## Organization scoping

- Every method on an organization-scoped table (`users`, `api_tokens`, `smtp_connections`)
  takes `int $organizationId` and puts it in the `WHERE` clause
  ([organizations/scope.md](../organizations/scope.md)).
- The only unscoped methods are those the specs require to cross organizations, and their
  doc comment names the spec that needs them:
  - lookups by a secret digest (`ApiTokenRepository::findByTokenHash()`), the token itself
    identifying the organization;
  - lookups by `username` / `email` / `id` on `users` for login, recovery and sessions;
  - the global admin's user management ([api/endpoints/admin-users.md](../api/endpoints/admin-users.md)).

## Locking

- `lock…()` methods add `FOR UPDATE` and must be called inside a transaction; their doc comment
  says so. They do not check it themselves.
- Which rows are locked, and in which order, is decided by the endpoint specs (e.g. the
  organization row before activating an SMTP connection).

## Time

- Repositories never call `NOW()`, `CURRENT_TIMESTAMP` or other database clock functions in
  their statements ([api/authentication/sessions.md](../api/authentication/sessions.md#clock)).
  Any "now" is a `DateTimeImmutable $now` argument, read by the caller from
  `Pidgey\Infra\WallClock`.
- `DateTimeImmutable` values are bound as `Y-m-d H:i:s` in UTC through the shared
  `Pidgey\Repository\Sql::dateTime()` helper, which converts to UTC first.
- Column defaults (`created_at`, `updated_at`) still use `CURRENT_TIMESTAMP` in the schema; the
  UTC session time zone keeps them UTC ([connection.md](connection.md#factory)).

## Secrets

- Repositories receive and store digests and ciphertext, never plain text: hashing a token
  and encrypting the SMTP password happen before the repository call, in the service.
- `SmtpConnectionRepository` reads and writes the `password` column as opaque bytes. The
  encryption mechanism is a pending decision of
  [email/smtp-connections.md](../email/smtp-connections.md); it blocks the service that
  encrypts, not the table or the repository.
- SQL errors are logged by the caller without bound values, so a digest or ciphertext never
  lands in a log line.

## Example

Illustrative only.

```php
namespace Pidgey\Repository;

final class SmtpConnectionRepository
{
    private const COLUMNS = 'id, organization_id, name, host, port, encryption, username, '
        . 'password, from_address, active, created_at, updated_at';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findInOrganization(int $organizationId, int $id): ?SmtpConnection
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM smtp_connections '
            . 'WHERE organization_id = :organization_id AND id = :id',
        );
        $statement->execute(['organization_id' => $organizationId, 'id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : SmtpConnection::fromRow($row);
    }
}
```

## Scope of the first implementation

Each table's implementation delivers its repository with `create()`, `findById()` (or
`findInOrganization()` for organization-scoped tables) and the lookups by unique key that its
table spec defines. Other methods are added by the endpoint issues that need them, following
this spec.

## Pending decisions

None.
