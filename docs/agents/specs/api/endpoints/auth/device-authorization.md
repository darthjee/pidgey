# Device Authorization

Purpose: index of the device-authorization endpoints, with the state machine and the rules
they all share (guards, trimming, expiry, clock, error answers, known limitations).

Device authorization is a second way to log in. A device that is **not** logged in (the
*requester*) asks to log in as `username`. A device where that user **is** logged in (the
*approver*) sees the request, confirms it with its own password, and approves it. The
requester polls until it gets a session.

Requests are stored in `authorization_requests`
([organizations/data-model/authorization-requests.md](../../../organizations/data-model/authorization-requests.md)).
The `AUTHORIZATION_REQUEST_*` settings and `TRUSTED_PROXY_HOPS` are in
[architecture/configuration.md](../../../architecture/configuration.md); the client IP and
user agent are resolved as in [conventions/client-ip.md](../../conventions/client-ip.md).
Shared auth rules (identifiers, password hashing, `DUMMY_DIGEST`) are in
[auth.md](../auth.md). Errors use [conventions/errors.md](../../conventions/errors.md).

| File | Content |
| --- | --- |
| [requester.md](device-authorization/requester.md) | `POST /auth/authorization-requests.json` (create) and `POST /auth/authorization-requests/{uuid}/poll.json` (poll and claim). |
| [approver.md](device-authorization/approver.md) | `POST /auth/authorization-requests/mine.json`, `POST /auth/authorization-requests/{uuid}/authorize.json` (with its per-request lockout) and `POST /auth/authorization-requests/{uuid}/deny.json`. |

## State machine

```
                 authorize               winning poll
   ┌──────┐   ─────────────▶ ┌──────────┐ ───────────▶ ┌────────┐
   │ open │                  │ approved │              │ logged │
   └──────┘                  └──────────┘              └────────┘
     │  │                                                  │
deny │  │ poll at/after expires_at,                        │ requester not approved
     │  │ or evicted by the open cap                       │ (pending/denied) at claim
     ▼  ▼                                                  ▼
 ┌────────┐  ┌─────────┐                              ┌────────┐
 │ denied │  │ expired │                              │ denied │
 └────────┘  └─────────┘                              └────────┘
```

- Every transition is a **conditional update** (`WHERE uuid = ? AND status = <from>`, or the
  row id for the open-cap eviction), so concurrent actors cannot both win: the one whose
  update affects 0 rows lost the race.
- Expiry is **lazy**: nothing flips rows in the background. An `open` row whose expiry has
  passed is flipped to `expired` (`resolved_at = now`) by the poll that sees it; `mine` hides
  it and `authorize` refuses it. `deny` does not check expiry (see
  [Expiry boundary](#expiry-boundary-and-clock)).
- `denied`, `expired` and `logged` are final.

## Guards

Every endpoint goes through the guard order of
[authentication/gate.md](../../authentication/gate.md#guard-order): origin check, gate, body
validation, handler.

- Every device-authorization endpoint is **`POST`**, so the
  [origin check](../../conventions/origin-check.md) applies to all of them, public ones
  included.
- **Public** (no gate): create and poll.
- **Gated:** `mine`, `authorize` and `deny`. Only `approved` users pass the gate; anyone
  else, including a `pending` user, gets the gate's uniform `401`.

## Success codes

Every endpoint answers **`201`** on success (create, poll, mine, authorize, deny), as the
Citadel contract does and as login, refresh and status do (see
[conventions/format.md](../../conventions/format.md#success-codes)).

## Input rules

- `username` (create) is **trimmed** before validation: not empty after trimming, max 255
  characters, and stored trimmed. The user lookup relies on the column collation, like login
  (see [auth.md](../auth.md#identifiers)).
- `password` (authorize) is a password that is only **checked**: a non-empty string, never
  trimmed, **no max length** (Citadel's 128-character cap is dropped), like `currentPassword`
  in [account.md](account.md).
- `pollToken` (poll): a non-empty string, taken as sent.
- A malformed `{uuid}` path segment is **not** a validation error: the route accepts any
  segment, and the lookup simply finds nothing (`404` on poll, the uniform `400` on authorize
  and deny).
- Body validation failures answer `400 VALIDATION_FAILED`
  ([conventions/validation.md](../../conventions/validation.md)).

## Error answers

Every business rejection of an endpoint uses **one** answer, so the caller cannot tell which
check failed:

| Endpoint | Answer |
| --- | --- |
| poll | `404 NOT_FOUND` — `Authorization request not found` |
| authorize | `400 BAD_REQUEST` — `Unable to authorize this request` |
| deny | `400 BAD_REQUEST` — `Unable to deny this request` |

Create never answers a business error: its response is the same whether or not the username
exists and whether or not the caller is over the rate limit (see
[requester.md](device-authorization/requester.md#enumeration-safety)). None of these
endpoints answers `401` for a business rule
([conventions/errors.md](../../conventions/errors.md#never-401-for-a-business-rule)).

## Expiry boundary and clock

- A request is **expired when `now >= expires_at`**, everywhere (poll, mine, authorize).
  Citadel mixes `<` and `>`; Pidgey uses this one boundary. `mine` therefore lists rows with
  `expires_at > now`.
- `now` is PHP's UTC clock, bound as a parameter; queries never call database clock functions
  (see [authentication/sessions.md](../../authentication/sessions.md#clock)).
- `deny` does **not** check expiry: an expired-but-still-`open` row can be denied.

## Known limitations

- **Device-login denial of service:** anyone can create `AUTHORIZATION_REQUEST_CREATE_LIMIT`
  (default 5) requests per window with a victim's username. The per-username limit then makes
  the victim's own requests unsaved (their polls always get `404`), and the open cap evicts
  the victim's oldest open request. Password login is not affected.
- **Timing:** create runs extra queries (open-cap check, eviction) when the username exists,
  so its timing can reveal whether the username exists. Accepted; the status and body never
  do.
- **Approval phishing:** an attacker's request for a victim's username shows up in the
  victim's "Authorizations" list. The approver UI shows the request IP, user agent and
  creation time; approving still requires the approver's password.
- Over-limit creates are not saved, so they do not count towards the limit.

## Pending decisions

- **Pending decision:** a short **verification code** shown on both devices and checked by
  the approver (in the style of the RFC 8628 `user_code`), against approval phishing.
- **Pending decision:** **retention** of `authorization_requests` rows, which keep the IP
  and user agent forever (e.g. a purge command for resolved or expired rows older than N
  days). To be decided with the operational endpoints and commands (#31).
