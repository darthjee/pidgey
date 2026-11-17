# Issue: Implement the API tokens admin endpoints (list, create, revoke)

## Description

The admin UI's API token pages (list, create, revoke) all hit endpoints that are documented in `docs/agents/api/endpoints/pidgey-admin/api-tokens.md` but never implemented, so creating a token at `/#/api-tokens/new` fails with `404`. This issue implements the three gated endpoints on top of the existing `api_tokens` persistence, following the `Pidgey\Auth` domain-class pattern.

### Context

Issue #216 (label `Bug`), opened on 2026-11-16. The endpoint contract lives in `docs/agents/api/endpoints/pidgey-admin/api-tokens.md`; token format and scopes in `docs/agents/api/authentication/api-tokens.md`; the table in `docs/agents/organizations/data-model/api-tokens.md`. Shared rules are in `docs/agents/api/endpoints/pidgey-admin.md` and `docs/agents/api/conventions.md`. Related closed work: #87 (auth token tables, entities and repositories) and #128 (JWT access token and `access_token` cookie), which built the persistence and the gate this issue depends on.

## Problem

`POST /api-tokens.json` answers `404` when the admin UI creates an API token at `/#/api-tokens/new`. The reported request body was `{ "name": " Moria ", "scopes": [ "send" ] }` and the response was an empty `HTTP/2 404`.

The payload is not the cause. The route does not exist: no API token route is registered on the router, and no handler class for API tokens exists. The frontend already calls the endpoints (`ApiTokensClient` uses `/api-tokens.json` for list and create, and `/api-tokens/:id.json` for revoke), and the persistence layer and the written contract already exist. Only the HTTP layer and the domain logic (token generation, listing and revoking) are missing.

The request therefore matches no route, the dispatcher throws `NotFoundException('Cannot POST /api-tokens.json')` and the error formatter returns the standard `404 NOT_FOUND` envelope. Per the routing rules a `*.json` path never falls back to the SPA redirect, so the failure is the documented "no match" 404 and not a rendering bug.

### Current behaviour (reference)

| Location | Behaviour today |
| --- | --- |
| `source/config/routes/organization.php` | Registers 8 routes, all `/smtp-connections*`. No `/api-tokens` route. The file docblock says the organization and API-token routes "join later". |
| `source/lib/Http/Handler/` | No `ApiTokensHandler` or `ApiTokenHandler`. Existing handlers: Account, HashRedirect, Health, Login, Logoff, Refresh, Register, SmtpConnection*, Static, Status. |
| `source/config/routes.php` | Requires `routes/frontend.php`, `operational.php`, `auth.php`, `organization.php`; `HashRedirectHandler` is the last fallback. |
| `source/lib/Http/FrontController.php` | `dispatch()` throws `NotFoundException('Cannot <METHOD> <path>')` when `Router::match()` returns `null`. |
| `docs/agents/request-handler/routing.md` | An unknown `*.json` path always gets the JSON 404, never the GET fallback redirect. |
| `frontend/assets/js/client/ApiTokensClient.js` | `COLLECTION_PATH = '/api-tokens.json'`; `list()` GET, `create()` POST, `revoke(id)` DELETE `/api-tokens/:id.json`. Expects `{ apiTokens }` and `{ apiToken }`. |
| `source/lib/Repository/ApiTokenRepository.php` | Has `create()`, `findInOrganization()` and `findByTokenHash()`. No list-by-organization and no revoke method. |
| `source/lib/Model/ApiToken.php` | Readonly entity with `tokenPrefix` and `tokenHash` only; the plain token is never part of the entity. |
| `source/migrations/0007_create_api_tokens.sql` | `api_tokens` table with unique `token_hash CHAR(64)`, `token_prefix VARCHAR(16)`, `scopes JSON`, `revoked_at DATETIME NULL`, FK to `organizations`. |
| `docs/agents/api/endpoints/pidgey-admin/api-tokens.md` | Full contract for the three endpoints, including the `201` create response carrying the plain `token` once. Documented as built, but not implemented. |
| `source/config/routes/auth.php` | `PATCH /auth/account.json` is the only existing example of a `Gate`-gated route in this area. |

## Expected Behavior

- `POST /api-tokens.json` answers `201` for a valid authenticated request, with the plain `token` present exactly once.
- `GET /api-tokens.json` answers `200` with the organization's tokens, revoked ones included, ordered by `id` ascending.
- `DELETE /api-tokens/:id.json` answers `204`, keeps the row and is idempotent.
- An anonymous or non-approved caller gets the gate's uniform `401` on all three routes.
- Invalid bodies answer `400 VALIDATION_FAILED` with one `details` entry per failed rule.
- A token of another organization answers `404 API token not found` on revoke, and the list never shows it.
- The stored `token_hash` equals the SHA-256 of the returned plain token.
- `token_hash` and `organizationId` never appear in any response body.
- Operator log lines exist for create and revoke and contain neither the plain token nor its digest.
- The admin UI flow at `/#/api-tokens/new` completes without a `404`.

## Solution

### Scope

The admin UI already ships the list page (`ApiTokensController`), the create page (`ApiTokenNewController`) and the created-token panel (`ApiTokenCreatedPanel`), so `GET /api-tokens.json` and `DELETE /api-tokens/:id.json` are broken today as well, not only `POST`. Fixing create alone would leave the user on a `404` list page right after creating a token, and leave no way to revoke a leaked token.

**In scope:** the full surface (list, create, revoke) in a single issue: the three routes, the two handlers, `Pidgey\Auth\ApiTokens`, `ApiTokenValue`, `ApiTokenRules`, `CreatedApiToken`, `ApiTokenPayload`, the two new repository methods, the `Container` wiring, the operator log constants and their tests.

**Out of scope:**

- Rejecting a revoked token on `POST /v1/messages`: that route does not exist yet, so the expectation belongs to the issue that builds `/v1`.
- The `Cache-Control: no-store` Pending decision (`api/pending.md`).
- The pagination Pending decision; the list stays `{ "apiTokens": [...] }`.
- Any change to the endpoint contract (`api-tokens.md`) or to the frontend beyond fixing an actual divergence (none was found).
- Updating `last_used_at`, which is set by `/v1` usage.

### Proposal

New route registrations in `source/config/routes/organization.php`:

```php
$router->get('/api-tokens.json', ApiTokensHandler::class, [Gate::class]);
$router->post('/api-tokens.json', ApiTokensHandler::class, [Gate::class]);
$router->delete('/api-tokens/:id.json', ApiTokenHandler::class, [Gate::class]);
```

New domain class in `Pidgey\Auth`, alongside `Registration`, `Login` and `AccountEdit`:

```php
final class ApiTokens
{
    /** @return list<ApiToken> */
    public function list(int $organizationId): array;
    public function create(int $organizationId, string $name, array $scopes): CreatedApiToken;
    /** @return bool Whether the token was active (and is now revoked). */
    public function revoke(int $organizationId, int $id): bool;
}
```

New secret helper in `Pidgey\Auth`, modelled on `RefreshTokenValue`:

```php
final class ApiTokenValue
{
    public const PREFIX = 'pdg_';                                              // stored verbatim in token_prefix
    public static function generate(): string;                                 // PREFIX + bin2hex(random_bytes(32))
    public static function hash(#[SensitiveParameter] string $plain): string;  // SHA-256 hex
}
```

#### Semantics

1. `GET /api-tokens.json` answers `200` with `{ "apiTokens": [ <item>, ... ] }`, the caller organization's tokens including revoked ones, ordered by `id` ascending.
2. `POST /api-tokens.json` validates the body, generates the plain token, stores only the SHA-256 digest and the display prefix, and answers `201` with the item plus `token` (the plain token), returned once and never again.
3. `DELETE /api-tokens/:id.json` sets `revoked_at` and answers `204` with no body. The row is kept. Revoking an already revoked token is idempotent and keeps the original `revoked_at`.
4. Every route is gated by `Gate`: the organization is always `AuthenticatedUser::organizationId()`, never request input. Every repository query filters by it.
5. The plain token is `pdg_` followed by the lowercase hex of 32 `random_bytes` (68 characters in total). `token_prefix` always stores exactly `ApiTokenValue::PREFIX` (`pdg_`), never any character of the secret, matching the contract examples (`"tokenPrefix": "pdg_"`), the data-model doc and `Fixtures::apiToken()`.
6. `name` is required, trimmed, not empty and at most 255 characters, and is not unique.
7. `scopes` is required, a non-empty array of strings, and each value must be a known scope name. In v1 the only scope is `send`. Unknown names and an empty array are rejected with `400 VALIDATION_FAILED`. Duplicates are removed before storing.
8. `Pidgey\Http\ApiTokenRules` validates the body (every failed rule reported at once, like `SmtpConnectionRules`) and deduplicates `scopes`. `Pidgey\Auth\ApiTokens` receives already-valid values and owns token generation, persistence and the revoke decision. The handlers own request parsing, the operator log line and response shaping.
9. One operator log line per successful state change: `api-token-create` with the new id, and `api-token-revoke` with the id when the token was active. Reads write none. Neither the plain token nor its digest is ever logged.
10. Unknown, out of range or another organization's `:id` answers `404 NOT_FOUND` with `API token not found`, the same body as an unknown id.

#### Token lifecycle

`ApiTokens::create()` creates the entity and the plain token together. Because `ApiToken` is immutable and holds only the digest and prefix, it returns the entity alongside the plain token in a dedicated result object (`CreatedApiToken`), and the handler passes both to the serializer. Without this the `token` field could never reach the `201` response.

#### Response shaping

The handler converts the entity to a `ApiTokenPayload` (a `JsonSerializable` value object, matching the existing `SmtpConnectionPayload` pattern). The create response uses a second serializer or a payload flag that adds `token` only on creation. `token_hash` and `organizationId` are never serialized.

### Design decisions

| Decision | Choice | Rationale |
| --- | --- | --- |
| Scope of the issue | Implement all three endpoints plus any required frontend alignment | The frontend client already calls all three; a partial surface leaves the UI broken. |
| Business logic location | New `Pidgey\Auth\ApiTokens` domain class plus `Pidgey\Auth\ApiTokenValue` | Mirrors `Registration`/`Login`/`AccountEdit` (thin handler, logic in an `Auth` class) and `RefreshTokenValue` (secret generation and digest). API tokens are credentials, so `Auth` is their natural home. |
| `Pidgey\Service` namespace | Not introduced here | `architecture.md` describes it, but no class exists today; creating it for one class in a bug fix would set a one-off precedent. Settling that layer belongs to a separate architecture issue. |
| Alternatives rejected | (A) new `ApiTokenService`; (B) orchestration in the handlers like `SmtpConnectionsHandler` | (A) is inconsistent with every neighbouring feature. (B) puts secret generation, the entity-plus-plain-token return and the idempotent revoke decision in the handler, where they are harder to unit-test. |
| Repository granularity | Add `listByOrganization()` and `revoke()` to the existing `ApiTokenRepository` | No new class needed; the repository already owns this table. |
| Scope validation | In `Pidgey\Http\ApiTokenRules`: reject unknown names with `400 VALIDATION_FAILED` and deduplicate | Matches `SmtpConnectionRules`, and lets `name` and `scopes` errors be reported together in one `details` list. |
| Plain token transport | `ApiTokens::create()` returns the entity plus the plain token in `CreatedApiToken`; the handler serializes | The entity is immutable and holds only the digest, so the plain token must travel separately. |
| Serialization | `ApiTokenPayload` value object, camelCase keys | Matches the existing `SmtpConnectionPayload` pattern and the camelCase contract. |
| Pagination | Not in this issue; keep `{ "apiTokens": [...] }` | `api/pending.md` lists pagination as an open Pending decision and the spec states v1 lists are unpaginated. The frontend client sends no page parameter. |
| Frontend changes | Align only what diverges from `api-tokens.md`, no contract change | The client already matches the documented contract. |

#### Edge cases (pinned)

- `scopes` empty, unknown, or with duplicates: `400`, `400`, deduplicated respectively.
- `scopes` not an array (for example `"send"`): `400 VALIDATION_FAILED`.
- `scopes: ["admin"]` (the removed scope): `400 VALIDATION_FAILED`.
- Whitespace-only `name`: `400 VALIDATION_FAILED` after trimming.
- `name` with surrounding spaces, as in the reported request: trimmed and created successfully.
- Malformed `:id` (not matching `^[1-9][0-9]*$`): `400 BAD_REQUEST` with `Validation failed (numeric string is expected)`.
- Revoking an already revoked token: `204`, original `revoked_at` untouched, no second operator log line.
- A token of an organization with no approved user is created normally but refused on `/v1`.
- Two tokens may share a `name`.
- `name` not a string (`42`, `null`, `[]`): `400 VALIDATION_FAILED`, `name must be a string` (already handled by `FieldRules`).
- `scopes` a JSON object (`{"0":"send"}`): `400 VALIDATION_FAILED`, `scopes must be an array`. PHP decodes it to an array, so the rule must check `array_is_list()`.
- `scopes` with non-string elements (`[1]`, `[null]`, `[["send"]]`): `400 VALIDATION_FAILED` with the unknown-scope message (`each value in scopes must be one of the following values: send`); no extra message.
- Scope names are matched exactly: `"Send"` and `" send "` are `400 VALIDATION_FAILED` (no case folding, no trimming).
- Extra body fields (`organizationId`, `token`, `tokenPrefix`, ...) are silently ignored; the organization always comes from the session.
- A very large `scopes` array needs no dedicated cap: the existing `Request::MAX_BODY_BYTES` (1 MB, `413`) bounds it, and validation collapses it to at most `["send"]`.
- Two concurrent `DELETE`s of the same active token both answer `204` and exactly one `api-token-revoke` log line is written.
- A `token_hash` unique-index collision is not retried: with at least 256 bits of entropy it is not a practical concern, and it would surface as a plain `500`.
- Control characters or a NUL byte in `name` are accepted: the contract only requires trimmed, non-empty, at most 255 characters, and changing it is out of scope (possible follow-up).
- The 255 limit on `name` counts characters, not bytes (multibyte names are fine), matching `VARCHAR(255)` with utf8mb4.

### Backward compatibility

Low risk; the change is additive.

- Nothing depends on today's `404`: the only caller is the admin UI, which already expects the documented contract.
- No migration: `api_tokens` (migration 0007) is used as is. The application has never created rows in it (only `Fixtures::apiToken()` in tests), so no data migration is needed.
- `ApiTokenRepository` only gains methods; `create()`, `findInOrganization()` and `findByTokenHash()` are unchanged. The digest is SHA-256 hex in `CHAR(64)`, the same as `RefreshTokenValue::hash()`, so the future `/v1` lookup works as is.
- `RouteGuards` requires `Gate` or `ApiTokenAuth` on every non-public route; the three new routes carry `Gate`. No existing test asserts the routes are absent.
- `Container::apiTokenRepository()` already exists; only `apiTokens()` is added.
- `token_prefix` is the literal `pdg_`, so the UI's `{tokenPrefix}…` renders `pdg_…` as documented.

### Security & performance

#### Already covered by existing code (no new work)

- **CSRF:** the router-wide `OriginCheck` rejects cross-site `POST`/`DELETE` before the gate.
- **Organization isolation:** the organization comes only from `AuthenticatedUser`; every query filters by it; another organization's id answers `404`.
- **Authorization:** any approved member of the organization may manage its tokens; no `AdminCheck`, as per the contract (asserted by `RoutesSecurityTest`).
- **Hashing:** plain SHA-256 is sufficient because the secret carries 256 bits of entropy (not brute-forceable); no HMAC or pepper. The future `/v1` lookup goes through the unique `token_hash` index, never through a comparison of plain tokens.
- **Error logging:** `ErrorFormatter` logs only the exception class, message, file and line of an unhandled error, never the request body or the stack trace arguments.
- **Performance:** the list uses `ix_api_tokens_organization_id`; `random_bytes` and SHA-256 are negligible. The unpaginated list is a known Pending decision, out of scope.

#### Decisions

| Concern | Decision | Rationale |
| --- | --- | --- |
| Encoding | 32 random bytes, lowercase hex: `pdg_` + 64 characters | Same as `RefreshTokenValue`; double-click selects the whole token (base64url's `-` breaks selection); no padding or charset questions. |
| Plain token in memory | `#[SensitiveParameter]` on every parameter carrying the plain token: `ApiTokenValue::hash()` and the `CreatedApiToken` constructor | Keeps the secret out of stack-trace arguments and `var_dump`-style debugging. |
| Failure after the insert | Generate, then insert in a single step; no fallible work between the insert and the response; no transaction | An orphan active token whose secret nobody received is harmless and can be revoked from the list. |
| Token count per organization | No cap in this issue | A cap would change the contract; recorded as a possible follow-up with rate limiting. |
| Logging | Never log the plain token, its digest or the request body of `/api-tokens.json`; operator log lines carry only ids | Per `authentication/api-tokens.md`. A `token_hash` unique-index collision would put the digest in the PDO error message; with 256 bits of entropy that is accepted as negligible. |

### Implementation notes

- Register the routes in `source/config/routes/organization.php`, not in a new file: the routes-table test loads `config/routes.php` and asserts every documented endpoint resolves to its handler.
- Handlers follow the existing shape: extend `RequestHandler`, expose `static build(Container)`, dispatch on `$request->method()` in `process()`, and require `AuthenticatedUser::class` from the request.
- Use `PathId::parse($request, 'API token not found')` for the `:id` routes so the `400` and `404` behaviour matches SMTP connections.
- Add `apiTokens()` to `Container`, built from the existing `apiTokenRepository()` accessor.
- In `ApiTokenRules`, reuse `FieldRules` for `name` (`trim()->notEmpty()->maxLength(255)`) and add the `scopes` array check alongside it, since `FieldRules` models single string fields.
- `ApiTokenValue` copies `RefreshTokenValue`'s shape: static methods, `random_bytes` (at least 32 bytes), SHA-256, `#[SensitiveParameter]` on every parameter carrying the plain token.
- Do not create a `Pidgey\Service` namespace.
- `ApiTokenRepository::revoke()` must be organization-scoped and atomic: a single `UPDATE ... SET revoked_at = NOW() WHERE organization_id = ? AND id = ? AND revoked_at IS NULL`, using the affected-row count to know whether the token was active (no read-then-write), so concurrent revokes log once. The `404` versus idempotent `204` distinction comes from `findInOrganization()`.
- `ApiTokenRules` checks `scopes` with `is_array()` **and** `array_is_list()`, and treats any non-string element as an unknown scope.
- `ApiTokenRepository::listByOrganization()` returns entities ordered by `id`, revoked rows included; do not filter `revoked_at IS NULL`.
- Add `OperatorLog::API_TOKEN_CREATE` and `OperatorLog::API_TOKEN_REVOKE` constants next to the SMTP ones.
- Update `docs/agents/api/endpoints/pidgey-admin/api-tokens.md` only if the implementation settles the pending `Cache-Control: no-store` item; otherwise leave the doc untouched.
- Do not resolve the pagination Pending decision inside this issue.

### Testing

Integration, `source/tests/integration/Http/Handler`:

- `ApiTokensHandlerTest`: `GET` returns `200` with `apiTokens` in id order including revoked tokens; `POST` returns `201` with the plain `token`; the stored digest matches the SHA-256 of that token; `401` for an anonymous caller on both methods; `400 VALIDATION_FAILED` for empty, unknown and duplicate scopes, for a non-array `scopes`, for a blank `name` and for a `name` over 255 characters; whitespace in `name` is trimmed; the list only shows the caller organization's tokens.
- `ApiTokenHandlerTest`: `DELETE` returns `204`; the row stays with `revoked_at` set; a second `DELETE` returns `204` idempotently; `404 API token not found` for another organization's token; `400 BAD_REQUEST` for a malformed `:id`; `401` for an anonymous caller.
- `RoutesSecurityTest`: the three routes are gated by `Gate` and carry no admin check.
- `HandlerFactoryRoutesTest`: the three documented endpoints resolve to their handlers.

Integration, other folders (they hit the database, like `integration/Auth/RegistrationTest`):

- `source/tests/integration/Auth/ApiTokensTest` (`Pidgey\Auth\ApiTokens`): create stores the digest and `token_prefix = 'pdg_'` exactly, and returns the plain token in `CreatedApiToken`; the plain token never reaches the repository; an exception thrown during create does not expose the plain token in its trace arguments; revoke returns `true` once and `false` when already revoked; organization scoping on list and revoke.
- `source/tests/integration/Repository/ApiTokenRepositoryTest` (existing, extended): `listByOrganization()` ordering and inclusion of revoked rows, `revoke()` organization scoping, idempotency and that a second call on the same row affects no row, and `create()` round-trip.

Unit, `source/tests/unit`:

- `Auth/ApiTokenValueTest`: `pdg_` prefix followed by exactly 64 lowercase hex characters, uniqueness across calls, `hash()` is the SHA-256 hex, `PREFIX` is `pdg_`.
- `Http/ApiTokenRulesTest`: `name` trimming, length (counted in characters, multibyte included) and non-string values; `scopes` empty, non-array, JSON object (`{"0":"send"}`), non-string elements, unknown (including `admin`, `Send`, ` send `) and duplicates; all errors reported at once.
- `Http/ApiTokenPayloadTest`: the serialized shape, `token` present only on create, and `token_hash` and `organizationId` absent.

## Benefits

- The admin UI's API token flow (create, list, revoke) works end to end instead of answering `404`.
- Organizations can mint `send`-scoped credentials for the upcoming `/v1` sending API, and revoke leaked ones.
- The documented contract becomes true; the implementation follows the existing `Auth` domain-class and `SmtpConnection*` patterns rather than introducing a new layer.
