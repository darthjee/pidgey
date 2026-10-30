# Entities and UserStatus enum

Add the entities per [entities.md](../../../specs/persistence/entities.md).

- `Pidgey\Model\UserStatus`: string-backed enum with cases `Pending = 'pending'`, `Approved = 'approved'` and `Denied = 'denied'`.
- `Pidgey\Model\Organization`: `final readonly class` with public promoted properties `int $id`, `string $name`, `DateTimeImmutable $createdAt`, `DateTimeImmutable $updatedAt`, and `fromRow(array $row): self` built on `Row`.
- `Pidgey\Model\User`: `final readonly class` with `int $id`, `int $organizationId`, `string $username`, `string $email`, `string $passwordDigest`, `bool $isAdmin`, `UserStatus $status`, `DateTimeImmutable $createdAt`, `DateTimeImmutable $updatedAt`, and `fromRow()` built on `Row::int/string/bool/enum/dateTime` (as in the spec example).
- No helper methods (`isApproved()` etc.): deferred to the endpoint issues.
- Add a docblock on each class and method, matching the existing `lib/` style.

Unit tests in `source/tests/unit/Model/`:

- `OrganizationTest`, `UserTest`: a full row maps to every property with the right type (`is_admin` `0`/`1` → bool, `status` → enum, datetimes parsed as UTC); a missing column throws `LogicException`; for `User`, an unknown `status` value throws.
- Use `#[CoversClass]` / `#[UsesClass(Row::class)]` like the existing tests.

## Files to Change
- `source/lib/Model/UserStatus.php` — new enum.
- `source/lib/Model/Organization.php` — new entity.
- `source/lib/Model/User.php` — new entity.
- `source/tests/unit/Model/OrganizationTest.php` — new.
- `source/tests/unit/Model/UserTest.php` — new.
