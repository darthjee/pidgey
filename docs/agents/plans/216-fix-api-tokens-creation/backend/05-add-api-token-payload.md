# Add ApiTokenPayload

Serialize an `ApiToken` to the contract's camelCase item, matching `SmtpConnectionPayload`.

`Pidgey\Http\ApiTokenPayload` (final readonly, `JsonSerializable`), constructed with `ApiToken $apiToken` and an optional `#[SensitiveParameter] ?string $token = null`:

- `jsonSerialize()`: `id`, `name`, `tokenPrefix`, `scopes`, `createdAt`, `lastUsedAt`, `revokedAt` (dates via `Timestamp::format`, `null` kept), plus `token` **only** when the constructor got one (the create response). Check the exact field list and order against `docs/agents/api/endpoints/pidgey-admin/api-tokens.md`.
- Never `tokenHash` nor `organizationId`.
- `__debugInfo()` returns the serialized shape **without** `token`.

Tests (unit, `ApiTokenPayloadTest`): list shape; create shape adds `token`; `tokenHash`/`organizationId` absent in both; nullable dates serialize as `null`; `__debugInfo()` omits `token`.

## Files to Change
- `source/lib/Http/ApiTokenPayload.php` — new.
- `source/tests/unit/Http/ApiTokenPayloadTest.php` — new.
