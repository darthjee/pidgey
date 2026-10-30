# Enum and entity

Add the encryption enum and the entity, with unit tests.

- `Pidgey\Model\SmtpEncryption`: a string-backed enum shaped like `UserStatus`, with a doc
  comment naming `smtp_connections.encryption`. Cases:
  - `None = 'none'`
  - `Tls = 'tls'`
  - `Ssl = 'ssl'`
- `Pidgey\Model\SmtpConnection`: a `final readonly class` with public promoted properties.

  | Property | Type | Read with |
  | --- | --- | --- |
  | `id` | `int` | `Row::int` |
  | `organizationId` | `int` | `Row::int` |
  | `name` | `string` | `Row::string` |
  | `host` | `string` | `Row::string` |
  | `port` | `int` | `Row::int` |
  | `encryption` | `SmtpEncryption` | `Row::enum` |
  | `username` | `?string` | `Row::nullableString` |
  | `encryptedPassword` | `?string` | `Row::nullableString` on the `password` column |
  | `fromAddress` | `string` | `Row::string` |
  | `active` | `bool` | `Row::bool` |
  | `createdAt` | `DateTimeImmutable` | `Row::dateTime` |
  | `updatedAt` | `DateTimeImmutable` | `Row::dateTime` |

  - The `encryptedPassword` doc comment says it holds the ciphertext bytes, never plain text.
  - `hasPassword(): bool` returns `$this->encryptedPassword !== null`.
  - Doc comments follow `ApiToken`: a class summary, `@param` per property, and
    `fromRow()` with `@throws LogicException` / `UnexpectedValueException`.
  - If `Row::nullableString` rejects or alters arbitrary binary bytes, fix that before relying
    on it. It should return the string unchanged; check `Row::toString`.
- Unit tests in `SmtpConnectionTest`, modelled on `ApiTokenTest`:
  - `fromRow()` builds every property from a full row: `port` and `active` given as strings
    or ints, the enum, UTC dates, and binary password bytes kept unchanged;
  - `null` `username` / `password` become `null`;
  - a missing column throws `LogicException`;
  - an unknown `encryption` value throws `UnexpectedValueException`;
  - `hasPassword()` returns true with a password and false without.

## Files to Change
- `source/lib/Model/SmtpEncryption.php`: new enum.
- `source/lib/Model/SmtpConnection.php`: new entity.
- `source/tests/unit/Model/SmtpConnectionTest.php`: new unit test.
