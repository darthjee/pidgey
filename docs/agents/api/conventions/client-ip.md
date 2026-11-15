# Client IP and User Agent

Purpose: define how the API resolves the caller's IP address and user agent, so every
feature that records or throttles by client (device authorization, a future per-IP throttle)
uses the same rules.

Used by the [device-authorization create](../endpoints/auth/device-authorization/requester.md#post-authauthorization-requestsjson)
(`request_ip`, `request_user_agent`) and by the per-IP throttle pending in
[pending.md](../pending.md). The setting `TRUSTED_PROXY_HOPS` (default `1`) is in
[architecture/configuration.md](../../architecture/configuration.md).

## Client IP

With `h = TRUSTED_PROXY_HOPS`:

1. **`h = 0`:** `X-Forwarded-For` is **ignored**; the client IP is `REMOTE_ADDR` (the socket
   address).
2. **`h > 0` and `X-Forwarded-For` is present:** split the header on `,`, **trim** each
   entry, and take the entry at index **`max(0, len − h)`** (0-based), where `len` is the
   number of entries. With `a, b, c` and `h = 1` the IP is `c`; with `h = 2` it is `b`; with
   `h = 5` it is `a`.
3. **Fallback to `REMOTE_ADDR`** when the header is missing, or when the selected entry is
   not a valid IPv4 or IPv6 address (`filter_var($entry, FILTER_VALIDATE_IP)`; an empty entry
   is invalid). There is no further search through the other entries.

The resolved IP is stored up to **45 characters** (the `VARCHAR(45)` column; a valid IPv4 or
IPv6 address is never longer).

### Warning: spoofing

Each trusted proxy appends the address it received the request from, so the last `h` entries
are written by trusted proxies and everything before them is written by the client.

- With `h` **larger** than the real number of proxies in front of the app, the selected entry
  is one the client wrote: **clients can spoof their IP** through `X-Forwarded-For`.
- With `h` **smaller** than the real number of proxies, every request appears to come from a
  proxy.
- Set `TRUSTED_PROXY_HOPS` to `0` when no reverse proxy sits in front of the app (see
  [authentication/local-development.md](../authentication/local-development.md#production)).

## User agent

- The user agent is the `User-Agent` header, or `""` when the header is absent.
- It is **truncated to 512 characters** (the `VARCHAR(512)` column), on a **UTF-8 character
  boundary** (never in the middle of a multi-byte character).
- It is informational only (shown to the approver); it is never trusted for a security
  decision.

## Testable rules

| `TRUSTED_PROXY_HOPS` | `X-Forwarded-For` | `REMOTE_ADDR` | Client IP |
| --- | --- | --- | --- |
| `0` | `198.51.100.1, 203.0.113.9` | `10.0.0.2` | `10.0.0.2` (header ignored) |
| `1` | `198.51.100.1, 203.0.113.9` | `10.0.0.2` | `203.0.113.9` |
| `2` | `198.51.100.1, 203.0.113.9` | `10.0.0.2` | `198.51.100.1` |
| `2` | `203.0.113.9` | `10.0.0.2` | `203.0.113.9` (index `max(0, 1 − 2) = 0`) |
| `1` | (missing) | `10.0.0.2` | `10.0.0.2` |
| `1` | `198.51.100.1 ,   203.0.113.9  ` | `10.0.0.2` | `203.0.113.9` (entries trimmed) |
| `1` | `198.51.100.1, not-an-ip` | `10.0.0.2` | `10.0.0.2` (invalid entry, fallback) |
| `1` | `198.51.100.1, 2001:db8::1` | `10.0.0.2` | `2001:db8::1` (IPv6) |

The user agent is also covered: header missing (`""`), exactly 512 characters (kept), and a
longer value with a multi-byte character across the 512-character boundary (truncated on the
character boundary).

## Pending decisions

None.
