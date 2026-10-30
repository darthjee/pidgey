# 02 — Entity

Create `Pidgey\Model\ApiToken`, a `final readonly` class with public promoted properties:

| Property | Type | Read with |
| --- | --- | --- |
| `id` | `int` | `Row::int` |
| `organizationId` | `int` | `Row::int` |
| `name` | `string` | `Row::string` |
| `tokenPrefix` | `string` | `Row::string` |
| `tokenHash` | `string` | `Row::string` |
| `scopes` | `list<string>` (docblock) | `Row::stringList` |
| `createdAt` | `DateTimeImmutable` | `Row::dateTime` |
| `lastUsedAt` | `?DateTimeImmutable` | `Row::nullableDateTime` (or the existing nullable helper) |
| `revokedAt` | `?DateTimeImmutable` | same |

`fromRow(array $row): self` follows `User::fromRow()`. Docblocks match the existing
entities and note that `tokenHash` is the stored digest, never the plain token.

Unit test `source/tests/unit/Model/ApiTokenTest.php`:

- a full row converts every column. `scopes` is given as the JSON string MySQL returns
  (`'["send"]'`) and comes back as `['send']`, and the dates come back as UTC;
- `last_used_at` / `revoked_at` set to null stay null, and set to values are parsed;
- a missing column throws `LogicException`;
- invalid `scopes` JSON throws `UnexpectedValueException`;
- `scopes` that is not a list of strings (an object, or a list holding a number) throws
  `UnexpectedValueException`.

## Files to Change

- `source/lib/Model/ApiToken.php`: new.
- `source/tests/unit/Model/ApiTokenTest.php`: new.
