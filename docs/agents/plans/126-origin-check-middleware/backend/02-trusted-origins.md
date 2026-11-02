# TrustedOrigins

Add `Pidgey\Http\TrustedOrigins`, the trusted-origins list shared by `OriginCheck` and, later, by the CORS step (#139).

- **Constructor:** `final readonly class TrustedOrigins`, with `__construct(list<string> $origins)`.
- **`static fromEnv(): self`:**
  - reads `ALLOWED_ORIGINS` through `Pidgey\Infra\Env`;
  - splits it on `,`, trims each entry and drops empty ones;
  - when no entries remain, falls back to `FRONTEND_BASE_URL`, reduced to `scheme://host[:port]` with `parse_url`. The port is kept only when present. An unparseable value gives an empty list;
  - entries are **not** validated here: `ConfigValidator` refuses boot on invalid ones (step 04).
- **`contains(string $origin): bool`:** exact string comparison. `'null'` is always `false`.
- **`all(): list<string>`:** returns the list.

## Tests

- `ALLOWED_ORIGINS` only;
- trimming and empty entries;
- the `FRONTEND_BASE_URL` fallback, with and without a port and with a path;
- both empty;
- exact match only (scheme, port and case differences do not match);
- `null`.

## Files to Change

- `source/lib/Http/TrustedOrigins.php`: new.
- `source/tests/unit/Http/TrustedOriginsTest.php`: new.
