# Product Owner Plan: Email test infrastructure: Mailpit instances, TLS fixtures and CI

Main plan: [plan.md](plan.md)

## Shared contracts

The product owner records the decisions of [plan.md](plan.md#shared-contracts):

- Mailpit `v1.32.0`;
- the CI-binary approach and the CI `localhost` key values;
- the auth constants `pidgey-test` / `pidgey-test-password`;
- the fixture file names.

## Implementation Steps

### Step 1 — Update `email/testing.md`

- "Mailpit instances":
  - the pinned tag is `v1.32.0`;
  - the auth user and password are the constants of `Pidgey\Tests\Support\Mailpit`,
    repeated in `MP_SMTP_AUTH` in compose and CI.
- "Reaching the instances": note that CI uses `localhost` with the same ports, since the
  instances run in the primary container.
- "TLS fixtures": list the file names, and note that the CA key is not committed
  (`generate.sh` makes a new CA each run).

### Step 2 — Tick `implement-email/testing.md`

- Replace the "certificate inline as PEM, or a small test image" wording in the CI item with
  the decision: the Mailpit binary in the CI base image, with instances started on
  `localhost`, because Mailpit reads TLS files only by path.
- In "Implementation work", tick items 1–4 and the `Mailpit` part of item 5. Leave the
  other support classes and item 6 open.

## Files to Change

- `docs/agents/specs/email/testing.md`
- `docs/agents/specs/implement-email/testing.md`
