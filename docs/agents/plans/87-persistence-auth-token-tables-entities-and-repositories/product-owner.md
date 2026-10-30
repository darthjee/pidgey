# Product-owner Plan: Persistence: auth token tables, entities and repositories

Main plan: [plan.md](plan.md)

## Shared contracts

This agent produces the spec rule on explicit creation timestamps (see
[plan.md](plan.md#shared-contracts)). Backend implements against it.

## Implementation Steps

### Step 1 — Record the explicit-timestamp rule

Add a bullet to the *Time* section of `docs/agents/specs/persistence/repositories.md`.

- **Rule:** a creation timestamp that the application later compares in PHP is an explicit
  `DateTimeImmutable` argument of `create()`, read by the caller from
  `Pidgey\Infra\WallClock`.
- **Why:** the start and the expiry of a token or request must come from the same clock (see
  [api/authentication/sessions.md#clock](../../specs/api/authentication/sessions.md#clock)).
- **Columns covered:**
  - `refresh_tokens.issued_at`;
  - `password_reset_tokens.created_at` (recover cooldown);
  - `authorization_requests.created_at` (rate limits).
- **Unchanged:** the schema keeps `DEFAULT CURRENT_TIMESTAMP`, and every other
  `created_at`/`updated_at` keeps relying on it.

Keep the existing bullets unchanged; "Pending decisions" stays "None".

## Files to Change

- `docs/agents/specs/persistence/repositories.md` — new bullet in *Time*.

## Notes

- This is a spec clarification, not a schema change. No table spec changes.
