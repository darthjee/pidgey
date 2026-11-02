# Secret keys and JWT HS256
Implement `api/authentication/access-token.md` (Claims, Signing, Verification, Clock, Key rotation) in a new `Pidgey\Auth` namespace (`source/lib/Auth/`).

- `Base64Url`: `encode(string): string` (URL-safe alphabet, no padding) and `decode(string): ?string` that is **strict and canonical**: only `[A-Za-z0-9_-]`, no padding, a length that is not `4n+1`, and `encode(decoded) === input`; otherwise `null`.
- `SecretKeys` (final readonly): `current` and `previous` (list); `fromEnv()` reads `SECRET_KEY` and `PREVIOUS_SECRET_KEYS` (split on `,`, entries trimmed, empty entries ignored; the configuration validation already refuses bad values). `verificationKeys()` returns current then previous, in order.
- `AccessTokenClaims` (final readonly): `int $userId` (`sub`), `string $sessionId` (`sid`), `int $issuedAt`, `int $expiresAt`. `username` and `isAdmin` are not part of it: they are never trusted.
- `AccessToken` (or `AccessTokenService`): built from `SecretKeys`, the TTL (`ACCESS_TOKEN_TTL`, default 900) and a `WallClock`.
  - `issue(int $userId, string $sessionId, string $username, bool $isAdmin): string`: header is the fixed `{"alg":"HS256","typ":"JWT"}`; payload `sub`, `sid`, `username`, `isAdmin`, `iat = now`, `exp = iat + ttl`; signed with the current key only; three base64url segments.
  - `verify(string $token): ?AccessTokenClaims`, the strict rules in order:
    1. exactly three `.`-separated segments;
    2. each segment decodes with `Base64Url::decode()`;
    3. header segment `===` the encoding of the fixed header;
    4. expected signature `Base64Url::encode(hash_hmac('sha256', "<h>.<p>", $key, true))` compared with `hash_equals()`, current key first, then each previous key; no match → `null`;
    5. only then decode the payload (`json_decode` with `JSON_BIGINT_AS_STRING` and a small depth, objects as `stdClass` so a JSON array is rejected): a JSON object; `sub` an `int >= 1`; `sid` matching the lowercase UUID pattern; `iat` and `exp` `int`s (a float or a big-int string fails `is_int`); `iat <= now`; `exp > now`; `exp - iat <= ttl`;
    6. unknown claims ignored.
  - `now` is `WallClock::now()->getTimestamp()`.
- Tests (`tests/unit/Auth/`): round trip; token signed with a previous key verifies; new tokens are signed with the current key; rejections, each giving `null`: two or four segments, empty segment, padding `=`, `+`/`/`, a non-canonical encoding (changed trailing bits), any other header (`alg: none`, `HS512`, extra `kid`, different key order or whitespace), tampered payload, tampered signature, wrong key, payload not JSON, a JSON array, `sub` missing / `0` / `"1"` / `1.0` / beyond `PHP_INT_MAX`, `sid` uppercase / malformed, `iat` in the future, `exp == now`, `exp < now`, `exp - iat > ttl`; `Base64Url` unit cases.

## Files to Change
- `source/lib/Auth/Base64Url.php` — new.
- `source/lib/Auth/SecretKeys.php` — new.
- `source/lib/Auth/AccessTokenClaims.php` — new.
- `source/lib/Auth/AccessToken.php` — new: issue and verify.
- `source/tests/unit/Auth/Base64UrlTest.php`, `SecretKeysTest.php`, `AccessTokenTest.php` — new.
