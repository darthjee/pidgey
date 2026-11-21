# Idempotency service

Add the decision logic as a class independent of HTTP, so the handler only calls it.

- `Email/IdempotencyKeys.php` (or `Http/`-free namespace of your choice):
  - key format check (`/^[\x21-\x7E]{1,255}$/D`) and the SHA-256 `key_hash`;
  - the `request_hash` normalization of the shared contract;
  - `begin(organizationId, key, message)` returning one of: *replay* (status + body), *reused* (`422`), *in use* (`409`), or a *reservation* handle; it handles the stale lease (completing the row as the stored `504 SMTP_TIMEOUT` envelope, then replaying it), the duplicate-key race (re-read and decide again) and the bounded cleanup after a successful reservation;
  - `finish(reservation, status, body, smtpStarted)` applying the store/release classification of the shared contract.
- Constants: TTL `86400`, lease `300`, cleanup batch `100`.
- Unit tests with a fake clock and an in-memory or mocked repository: each branch of `begin`, the store/release table per code, the race path, the lease boundary (exactly at `lease_until`), expiry, the normalization (field order, absent field as `null`, Unicode).

## Files to Change

- `source/lib/Email/IdempotencyKeys.php` — new service (plus small value objects for the decision if needed).
- `source/lib/Container.php` — wire it.
- `source/tests/unit/Email/IdempotencyKeysTest.php` — new.
