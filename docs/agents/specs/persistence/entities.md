# Entities

Purpose: define the `Pidgey\Model\` classes that represent a table row in PHP.

## Shape

- One entity per table, named after the singular of the table:

  | Table | Entity |
  | --- | --- |
  | `organizations` | `Organization` |
  | `users` | `User` |
  | `refresh_tokens` | `RefreshToken` |
  | `password_reset_tokens` | `PasswordResetToken` |
  | `authorization_requests` | `AuthorizationRequest` |
  | `account_edit_lockouts` | `AccountEditLockout` |
  | `api_tokens` | `ApiToken` |
  | `smtp_connections` | `SmtpConnection` |

- `final readonly class`, with **public promoted constructor properties**, one per column.
  No setters, no behaviour that touches the database. A change is a repository call; the
  repository returns a new instance when the caller needs the updated row.
- Property names are the camelCase of the column (`organization_id` → `organizationId`,
  `is_admin` → `isAdmin`).
- Small pure helpers derived from the row are allowed (e.g. `User::isApproved()`,
  `ApiToken::isRevoked()`, `SmtpConnection::hasPassword()`); anything needing a clock takes
  `DateTimeImmutable $now` as a parameter (e.g. `RefreshToken::isExpiredAt($now)`).
- Entities know nothing about JSON or the API. Turning an entity into a response body is the
  HTTP layer's job, which is how `password_digest`, token hashes and the SMTP password never
  reach a response by accident.

## `fromRow()`

Every entity declares:

```php
/** @param array<string, mixed> $row */
public static function fromRow(array $row): self;
```

- `$row` is a `FETCH_ASSOC` row keyed by **column name** (snake_case), as selected by the
  repository's column list ([repositories.md](repositories.md#column-lists)).
- A missing column is a programmer error: `fromRow()` throws `LogicException` naming the
  entity and the column.
- Conversions go through the shared `Pidgey\Model\Row` helper so every entity converts the
  same way:

  | Column type | PHP type | Conversion |
  | --- | --- | --- |
  | `BIGINT`, `INT` | `int` | Already `int` (native prepares, see [connection.md](connection.md#factory)); `Row::int()` still casts and checks. |
  | `VARCHAR`, `CHAR` | `string` | As is. |
  | `VARBINARY` | `string` | As is (raw bytes). |
  | `BOOLEAN` | `bool` | `Row::bool()`: `TINYINT(1)` `0`/`1` → `false`/`true`. |
  | `DATETIME` | `DateTimeImmutable` | `Row::dateTime()`: parsed as `Y-m-d H:i:s` in **UTC**. |
  | `ENUM` | backed enum | `Row::enum(Enum::class, …)`: `Enum::from()`; an unknown value throws. |
  | `JSON` | `list<string>` | `Row::stringList()`: `json_decode(…, flags: JSON_THROW_ON_ERROR)`, checked to be a list of strings. |
  | any nullable column | `?T` | `null` stays `null`. |

## Enums

`ENUM` columns, and `VARCHAR` columns restricted to a fixed set, map to string-backed PHP
enums in `Pidgey\Model\`, whose cases' values are the database values:

| Column | Enum |
| --- | --- |
| `users.status` | `UserStatus` (`Pending`, `Approved`, `Denied`) |
| `authorization_requests.status` | `AuthorizationRequestStatus` (`Open`, `Approved`, `Denied`, `Logged`, `Expired`) |
| `smtp_connections.encryption` | `SmtpEncryption` (`None`, `Tls`, `Ssl`) |
| `refresh_tokens.revoked_reason` | `RevokedReason` (`Rotated`, `Logout`, `PasswordReset`, `ReplayDetected`, `AdminDenied`, `AdminEdit`) |

## Secrets in entities

- Entities hold the stored form only: `User::$passwordDigest`, `…::$tokenHash`,
  `AuthorizationRequest::$pollTokenHash`, and `SmtpConnection::$encryptedPassword` (the
  `password` column's ciphertext bytes, renamed so it is never mistaken for plain text).
- A plain-text secret (a token being issued, a password being set) is never put in an
  entity.

## Example

Illustrative only.

```php
namespace Pidgey\Model;

final readonly class User
{
    public function __construct(
        public int $id,
        public int $organizationId,
        public string $username,
        public string $email,
        public string $passwordDigest,
        public bool $isAdmin,
        public UserStatus $status,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            Row::int($row, 'id'),
            Row::int($row, 'organization_id'),
            Row::string($row, 'username'),
            Row::string($row, 'email'),
            Row::string($row, 'password_digest'),
            Row::bool($row, 'is_admin'),
            Row::enum($row, 'status', UserStatus::class),
            Row::dateTime($row, 'created_at'),
            Row::dateTime($row, 'updated_at'),
        );
    }
}
```

## Pending decisions

None.
