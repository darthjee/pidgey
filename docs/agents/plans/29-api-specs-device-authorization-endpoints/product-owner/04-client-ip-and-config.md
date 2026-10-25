# Client IP and configuration

- Create `api/conventions/client-ip.md` (decision 3): `TRUSTED_PROXY_HOPS = 0` uses
  `REMOTE_ADDR`; otherwise split `X-Forwarded-For` on `,`, trim, take index
  `max(0, len − h)`; missing header or invalid IP (`FILTER_VALIDATE_IP`) falls back to
  `REMOTE_ADDR`; IP max 45 characters; user agent from `User-Agent` or `""`, truncated to 512
  characters on a UTF-8 boundary; spoofing warning. Add a testable-rules table (hops 0/1/2,
  missing header, spaces, invalid entry).
- Add it to the `api/conventions.md` index.
- `architecture/configuration.md`: add the six `AUTHORIZATION_REQUEST_*` settings (decision 2)
  to the Authentication table; add the `TRUSTED_PROXY_HOPS` non-negative-integer exception to
  Rules; remove "Settings for device authorization (#29) are added by that spec."
- `api/authentication/local-development.md` and `api/pending.md` (per-IP throttle): link
  `conventions/client-ip.md` for IP resolution.

## Files to Change
- `docs/agents/specs/api/conventions/client-ip.md` — new.
- `docs/agents/specs/api/conventions.md` — index row.
- `docs/agents/specs/architecture/configuration.md` — settings and rule.
- `docs/agents/specs/api/authentication/local-development.md` — link.
- `docs/agents/specs/api/pending.md` — link.
