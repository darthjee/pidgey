# Registration service and register handler
**`Pidgey\Auth\Registration`** (built in `Container::registration()` from the transaction, the organization and user repositories, `sessions()` and `userApprovalRequired()`):

- `register(string $username, string $email, #[SensitiveParameter] string $password, string $organizationName): SessionTokens`
  1. `precheck($username, $email)`: `findTaken()`; when a row matches, throw `ConflictException('username is not available', 'USERNAME_TAKEN')` if its username matches (compare in SQL or by re-running a username lookup; do **not** compare in PHP with `===`, since the collation is case/accent-insensitive), otherwise `ConflictException('email is not available', 'EMAIL_TAKEN')`.
  2. `Passwords::hash($password)`.
  3. `create(...)`.
- `create(string $username, string $email, #[SensitiveParameter] string $digest, string $organizationName): SessionTokens` (public, so the race test can skip the pre-check): inside `Transaction::run()`: `OrganizationRepository::create($organizationName)`, `UserRepository::create($orgId, $username, $email, $digest, $approvalRequired ? UserStatus::Pending : UserStatus::Approved)`, `Sessions::start($user)`. Catch `PDOException` with SQLSTATE `23000` **outside** the transaction (so it has been rolled back), then run `precheck()` again, which throws the matching `409`. If the re-check finds nothing (some other 23000), rethrow the original exception.

**`Pidgey\Http\Handler\RegisterHandler`** (`build()` takes `registration()` and `accessToken()`):
- `Validator` on `Request::json()`:
  - `username`: `trim()->notEmpty()->maxLength(255)`;
  - `email`: `trim()->email()->maxLength(255)`;
  - `password`: `minLength(8)->maxBytes(72)->noNulByte()` (never trimmed);
  - `organizationName`: `trim()->notEmpty()->maxLength(255)`.
  - Check messages against `conventions/validation.md` (e.g. `organizationName must be shorter than or equal to 255 characters`).
- `Registration::register()`, then `SessionResponse::created($tokens, $accessToken->ttl())`.

Tests:
- **Integration (`tests/integration/Auth/RegistrationTest.php`, no outer transaction, tables truncated like `SessionsTest`)**: success with `USER_APPROVAL_REQUIRED` unset → `pending`; `=false` → `approved`; `=FALSE`/`0` → `pending`; organization created with the trimmed name; `is_admin=false`; one active refresh token for the user; password stored as a verifiable digest (not plain). Pre-check: username taken → `USERNAME_TAKEN`; email taken → `EMAIL_TAKEN`; both taken by different users → `USERNAME_TAKEN`; taken by a `denied` user → still `409`; `BOB` vs `bob` → `USERNAME_TAKEN`. **Race:** call `create()` with an existing username (then email) → the matching `409`, **no** new organization row, no new refresh token.
- **Handler (unit or integration through `FrontController`)**: every validation error reported at once (`400 VALIDATION_FAILED` with all details); whitespace-only username is empty; password not trimmed (`"  12345678  "` stored as is, verify with spaces); 73-byte password and NUL byte rejected; success `201` body + cookie.

## Files to Change
- `source/lib/Auth/Registration.php` — new.
- `source/lib/Http/Handler/RegisterHandler.php` — new.
- `source/lib/Container.php` — `registration()`.
- `source/tests/integration/Auth/RegistrationTest.php`, `source/tests/integration/Http/Handler/RegisterHandlerTest.php` (or unit) — tests.
