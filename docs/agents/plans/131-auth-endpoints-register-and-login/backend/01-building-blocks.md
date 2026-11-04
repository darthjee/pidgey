# Building blocks: conflict exception, repository methods, config readers
Add the small pieces the two services need, each with unit or repository tests.

- **`Pidgey\Http\ConflictException`** (`extends HttpException`, status `409`), constructor `(?string $message = null, ?string $code = null)` like `ForbiddenException`; default code `CONFLICT`. Unit test next to `ForbiddenExceptionTest`, and check the error envelope renders it (`ErrorFormatter`), e.g. `{"error":{"code":"USERNAME_TAKEN","message":"username is not available"}}` (follow the existing envelope shape).
- **`UserRepository::findTaken(string $username, string $email): ?User`**: the register pre-check, **one query** `SELECT … FROM users WHERE username = :username OR email = :email` with `ORDER BY (username = :username2) DESC LIMIT 1` (or equivalent), so a username match wins over an email match when two rows match. It relies on the `utf8mb4_0900_ai_ci` collation; no PHP lowercasing. Denied users count as taken (no status filter). Repository tests: username match, email match, both on different rows (username row returned), case/accent-insensitive match (`BOB` / `bób` vs `bob`), no match.
- **`UserRepository::updatePasswordDigest(int $id, string $digest): void`** (the digest is `#[SensitiveParameter]`), for the login rehash. Repository test.
- **`AccessToken::ttl(): int`**: exposes the `ACCESS_TOKEN_TTL` the service was built with, so the response sets the cookie `Max-Age` from the same value the JWT `exp` uses. Unit test.
- **`USER_APPROVAL_REQUIRED` reader:** a small `Pidgey\Infra\UserApproval::required(): bool` (or a static on an existing config class) returning `false` only when `Env::get('USER_APPROVAL_REQUIRED')` is exactly the string `false`; unset, `0`, `FALSE`, `no`, empty, anything else → `true`. Exposed through `Container::userApprovalRequired(): bool` so tests can drive it via the environment. Unit tests for each value.

## Files to Change
- `source/lib/Http/ConflictException.php` — new 409 exception.
- `source/lib/Repository/UserRepository.php` — `findTaken()`, `updatePasswordDigest()`.
- `source/lib/Auth/AccessToken.php` — `ttl()`.
- `source/lib/Infra/UserApproval.php` — new config reader.
- `source/lib/Container.php` — `userApprovalRequired()`.
- `source/tests/unit/Http/ConflictExceptionTest.php`, `source/tests/unit/Auth/AccessTokenTest.php`, `source/tests/unit/Infra/UserApprovalTest.php`, `source/tests/integration/Repository/UserRepositoryTest.php` — tests.
