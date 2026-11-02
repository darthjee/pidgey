# Product-owner Plan: Access token: JWT HS256 and `access_token` cookie

Main plan: [plan.md](plan.md)

## Shared contracts

- Claim formats, `verify()` result, clear-cookie line and boot refusal as in [plan.md](plan.md#shared-contracts).

## Implementation Steps

### Step 1 — Access-token claim formats
In `api/authentication/access-token.md#verification-strict-rules`, rule 5: make the formats exact. `sub` is a JSON integer `>= 1`; `sid` is a canonical lowercase UUID (8-4-4-4-12 hex, any version); `iat` and `exp` are JSON integers. Add that a float (`1.0`), a numeric string (`"1"`) or an integer beyond the platform's integer range is not an integer. In the Cookie section, give the clear line's fixed past date, `Expires=Thu, 01 Jan 1970 00:00:00 GMT`, and mirror it in `request-handler/request-response/response.md#setcookie` (`clear()`).

### Step 2 — Configuration and boot refusal
In `architecture/configuration.md`:
- `#development-key`: the `.env` line and the code constant now exist; replace "land with the access-token work (#128)" with where they live (root `.env`, `ConfigValidator::DEVELOPMENT_SECRET_KEY`).
- `#bin-check-config`: remove the "Web boot refusal not wired yet" bullet and say that the web boot runs the same validation (without the PHP version check) before routing, and refuses on any failure through the invalid-configuration path of `request-handler/front-controller.md#failures-outside-the-wrapper`. Note that every request, static files included, then needs a valid configuration.

## Files to Change
- `docs/agents/specs/api/authentication/access-token.md` — exact claim formats; the clear line's past date.
- `docs/agents/specs/request-handler/request-response/response.md` — the clear line's past date.
- `docs/agents/specs/architecture/configuration.md` — the development key's location; the web boot refusal is wired.

## Notes
- No new decision: everything here was agreed in the issue discussion.
