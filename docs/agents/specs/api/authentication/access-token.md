# Access Token

Purpose: define the JWT access token of an admin UI session, its strict verification rules,
the `access_token` cookie and secret-key rotation.

The access token is the short-lived credential of a session (see [sessions.md](sessions.md)).
It is a JWT signed with **HS256** and carried **only** in the `access_token` cookie. Settings
(`SECRET_KEY`, `PREVIOUS_SECRET_KEYS`, `ACCESS_TOKEN_TTL`) are defined in
[architecture/configuration.md](../../architecture/configuration.md).

## Claims

| Claim | Type | Meaning |
| --- | --- | --- |
| `sub` | integer | User id (`users.id`). |
| `sid` | UUID string | Session id: the `session_uuid` of the refresh-token chain (see [sessions.md](sessions.md)). |
| `username` | string | **Informational only**; the backend never trusts it. |
| `isAdmin` | boolean | **Informational only**; the backend never trusts it. |
| `iat` | integer | Issued at, Unix seconds. |
| `exp` | integer | Expiry, Unix seconds: `exp = iat + ACCESS_TOKEN_TTL` (default 900 s). |

`username` and `isAdmin` are always read from the database by the gate (see
[gate.md](gate.md)), never from the token.

## Signing

- The header is always the fixed value `{"alg":"HS256","typ":"JWT"}`.
- Signing always uses the **current** `SECRET_KEY`; retired keys are never used to sign.
- Encoding is base64url without padding for the three segments.

## Verification (strict rules)

Every rule below must hold; any failure is the uniform gate `401` (see [gate.md](gate.md)).

1. The token has **exactly three** segments separated by `.`.
2. Each segment is **strict, canonical base64url**: the URL-safe alphabet only, no padding,
   no other characters, and re-encoding the decoded bytes gives back **the same segment**
   (no alternative encodings of the same bytes).
3. The header segment must be **byte for byte equal** to the base64url encoding of the fixed
   header `{"alg":"HS256","typ":"JWT"}`. Any other header is rejected, so `none`, every other
   algorithm and header parameters such as `kid`, `jku`, `x5u`, `x5c` or `jwk` are **never
   honored** (they cannot select a key or an algorithm).
4. The expected signature is computed with HMAC-SHA256 over `<header>.<payload>` (the two
   segments as received), base64url-encoded, and compared with the signature segment in
   constant time (`hash_equals`): first with the current `SECRET_KEY`, then with each
   `PREVIOUS_SECRET_KEYS` entry in order. No match → rejected.
5. Only **after the signature matches** is the payload decoded. It must be a JSON
   **object** with:
   - `sub` an integer;
   - `sid` a well-formed UUID string;
   - `iat` and `exp` integers;
   - `iat <= now`;
   - `exp > now`, with **no leeway**;
   - `exp - iat <= ACCESS_TOKEN_TTL`.
6. No other claim is trusted. Unknown claims are ignored.

### Clock

`now` comes from **PHP's clock, in UTC**. There is no leeway: a single server both signs
and verifies, so there is no clock skew to absorb. The same clock rule applies to every
session time comparison (see [sessions.md](sessions.md#clock)).

## Cookie

The access token is set as:

```
Set-Cookie: access_token=<jwt>; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=<ACCESS_TOKEN_TTL>
```

- `Max-Age` equals `ACCESS_TOKEN_TTL`, the same value as the JWT lifetime.
- No `Domain` attribute: the cookie is host-only.
- The attributes are the **same in every environment**; there is no switch that turns off
  `Secure` (see [local-development.md](local-development.md)).
- The token is **never** put in a response body. The frontend cannot read it, which is why
  `isAdmin` is returned in the JSON payloads (see [gate.md](gate.md#shared-user-payload)).
- Logout clears the cookie:
  `Set-Cookie: access_token=; Path=/; Max-Age=0; Expires=<past date>` (with the same
  `HttpOnly; Secure; SameSite=Strict` attributes).
- `SameSite=Strict` and the [origin check](../conventions/origin-check.md) together are the
  CSRF protection for cookie-authenticated requests.

## Key rotation

Verification tries the current key and then each retired key, so the signing key can be
rotated without logging anyone out:

1. Move the current `SECRET_KEY` into `PREVIOUS_SECRET_KEYS`.
2. Set a new `SECRET_KEY` and restart the app.
3. Remove the old key from `PREVIOUS_SECRET_KEYS` after at least `ACCESS_TOKEN_TTL`.

Dropping a key from `PREVIOUS_SECRET_KEYS` only invalidates the access tokens signed with it.
The client then gets a `401` and refreshes: refresh tokens are not signed with the key, so
rotation never logs anyone out unless their refresh token has also expired.

### Compromised key

The procedure above is for **routine** rotation only. When a key has leaked:

1. **Do not** move the leaked key into `PREVIOUS_SECRET_KEYS`.
2. Replace `SECRET_KEY` with a new key and drop the leaked key **at once** (also from
   `PREVIOUS_SECRET_KEYS` if it is there), then restart the app.
3. Every access token signed with the leaked key becomes invalid; clients get a `401` and
   refresh, which issues a token signed with the new key.

Tokens forged with the leaked key before the switch still need a **live `sid`** to pass the
gate (see [gate.md](gate.md#the-gate)), so a forger cannot open a session that does not exist.

Key rules (minimum length, boot refusal) are in
[architecture/configuration.md](../../architecture/configuration.md#secret-keys).

## Why hand-rolled

JWT support is **hand-rolled** (base64url, `hash_hmac('sha256')`, `hash_equals`; roughly 60
lines), with no JWT library:

- The dependency policy ([architecture/dependencies.md](../../architecture/dependencies.md))
  keeps the runtime to `php` and `monolog`; it is unchanged.
- `firebase/php-jwt` and `lcobucci/jwt` were considered and rejected: each adds a runtime
  dependency to track for advisories, for a single fixed algorithm.
- The strict rules above remove the usual hand-rolled pitfalls (`alg: none`, algorithm
  confusion, key selection by header, non-constant-time comparison).

## Pending decisions

- **Pending decision:** renaming the cookie to `__Host-access_token`. The `__Host-` prefix
  makes the browser enforce `Secure`, `Path=/` and no `Domain`; the Citadel contract uses
  `access_token`, which is kept until this is decided.
