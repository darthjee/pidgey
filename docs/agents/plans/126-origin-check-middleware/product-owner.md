# Product Owner Plan: Origin check middleware

Main plan: [plan.md](plan.md)

## Shared contracts

The parsing rules, trusted-origins rules and config failure lines in [plan.md](plan.md#shared-contracts) are what the specs must state. The backend implements them as written.

## Implementation Steps

### Step 1 — Origin-check parsing details

In `docs/agents/specs/api/conventions/origin-check.md`:

- **Rules section:** state that the check applies to every method except `GET`/`HEAD`/`OPTIONS`, `PUT` included, and to any unknown method (fail closed).
- **New "Parsing" subsection:**
  - the accepted `Origin` form (`http`/`https`, host, optional port, no path/query/fragment/user info);
  - default ports;
  - bracketed IPv6;
  - case-insensitive host comparison;
  - a `Host` without a port taking the `Origin` scheme's default port;
  - a missing or unparseable `Host` rejecting the request;
  - exact, case-sensitive `Sec-Fetch-Site` values.
- **Trusted origins:**
  - `ALLOWED_ORIGINS` entries are trimmed and empty entries dropped;
  - the `FRONTEND_BASE_URL` fallback uses only its `scheme://host[:port]` part.
- **New "Testing" section:** list the unit cases from the issue (every rule, `PUT` and unknown methods, `Origin: null`, unparseable `Origin`, `Host`/port matching including default ports and IPv6), plus one integration case: a cross-site `POST` through `FrontController` gets the `403` envelope.

### Step 2 — Configuration rules

In `docs/agents/specs/architecture/configuration.md`, Origins section:

- **Bare-origin rule:** each `ALLOWED_ORIGINS` entry must be a bare `scheme://host[:port]` origin, with no path, not even a trailing `/`.
- **Failure lines:** add `ALLOWED_ORIGINS: invalid origin` and `ALLOWED_ORIGINS: not https` to the list of failures that refuse boot, and to the `bin/check-config` output examples.
- **Environment rule:** the `not https` check is skipped only when `PHP_ENV` is exactly `development` or `test`, matching #125.

In `docs/agents/specs/testing/backend.md`, add the `ConfigValidator` cases:

- valid `https` entry;
- entry with a path, or a trailing `/`;
- no scheme;
- `ftp://` scheme;
- `http` entry in production and in `development`/`test`;
- unset `PHP_ENV`.

## Files to Change

- `docs/agents/specs/api/conventions/origin-check.md`: methods, parsing, trusted-origin trimming and fallback, testing.
- `docs/agents/specs/architecture/configuration.md`: the `ALLOWED_ORIGINS` validation rules and failure lines.
- `docs/agents/specs/testing/backend.md`: the `ConfigValidator` cases.

## Notes

- **No code changes.** Keep `Pending decisions: None.` in `origin-check.md`.
- **Leave CORS alone:** the CORS step (`front-controller.md#cors-step-pidgeyhttpcors`) belongs to #139.
- **Overlap with #125:** if #125 has already merged its `PHP_ENV` note into `configuration.md`, reuse its wording instead of restating it.
