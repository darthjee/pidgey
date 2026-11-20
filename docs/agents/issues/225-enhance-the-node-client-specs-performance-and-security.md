# Issue: Enhance the Node client specs: performance and security

## Description

Settle the performance and security points of the Node client specs (`docs/agents/specs/node-client.md` and `docs/agents/specs/node-client/*.md`, epic #219) that were not reviewed as a topic when the specs were written (#224). This issue covers three of them: the supply chain, the API token on the CLI command line, and the public OpenAPI route. The remaining runtime points (cleartext `http://`, the retained response body, the stdin size cap, connection reuse and the worst-case call duration) moved to #231.

- Parent epic: #219. Follows #224 (specs written and merged).
- Related: #221 (Idempotency-Key), #223 (publishing and provenance), #231 (remaining runtime topics).
- Owners: `product-owner` (spec files) with `security` (review).

## Problem

The specs carry `**Pending decision** (#225)` markers in five files:

- **Supply chain** (`packaging.md`): npm 2FA, provenance, and confirming devDependencies (`openapi-typescript`, `typescript`, the contract-test schema validator) never ship.
- **Token on the command line** (`cli.md`): `--token` is visible to every local user in process listings (`ps`, `/proc/<pid>/cmdline`) and lands in shell history.
- **Public OpenAPI route** (`openapi.md`): `GET /v1/openapi.json` is unauthenticated; it must reveal nothing beyond the public contract, and its caching headers are undecided.
- Four more markers (`library.md` ×2, `errors-and-retry.md`, `cli.md` stdin) belong to topics moved to #231.

## Expected Behavior

- Each of the three topics is decided in its spec file under `docs/agents/specs/node-client/`, replacing its `#225` marker.
- `cli.md` no longer has a `--token` flag; it documents `--token-file` and `PIDGEY_TOKEN`, and `testing.md` lists the matching CLI specs.
- `openapi.md` states the static-file rule, the content rules (enforced by the route test) and the no-cache-headers rule.
- `packaging.md` states the `files` allowlist, the `npm pack --dry-run` check, 2FA, the granular token, the provenance goal and frozen installs.
- The four markers not settled here point to `#231`; the hub pending table is updated accordingly.
- The `security` agent has reviewed the decisions in the PR. Any decision needing a backend change beyond the already planned OpenAPI route is spun off as its own issue.

## Solution

Record the three decisions below in the spec files, re-point the four unsettled markers to #231, and have the `security` agent review the change.

### Supply chain (`packaging.md`)

Informed by darthjee/navi, whose `navi-hey-client` package (`clients/node/`) is the closest existing model.

- **What ships.** Each package declares a `files` allowlist in `package.json`; no `.npmignore`, as in navi. devDependencies (`openapi-typescript`, `typescript`, the contract-test schema validator, ESLint, Jasmine, c8) are never installed by consumers, and specs, fixtures, the `types/` sources, coverage and lint config never enter the tarball.
- **Pack check.** CI runs `npm pack --dry-run` for each package and fails when the file list contains anything outside the allowlist (navi has no such check and lists that as a gap). The fuller smoke test (install the packed tarball, run `pidgey --help`, type-check the `.d.ts`) stays with #220.
- **2FA and token.** 2FA is required on the maintainer's npm account and in each package's publishing settings. CI publishes with a **granular** npm token limited to `pidgey-client` and `pidgey-cli`, with an expiry date, never a classic long-lived automation token (navi's single long-lived `NPM_TOKEN` is the pattern to avoid). The token's location, rotation and the release procedure are #223's.
- **Provenance.** The goal is provenance-attested releases: `npm publish --provenance`, or npm trusted publishing (OIDC), whenever the mechanism chosen in #223 supports it. If that mechanism cannot attest, the spec records the gap explicitly rather than dropping the goal silently. #223 confirms feasibility.
- **Frozen installs.** CI installs with the committed lockfile frozen (`--frozen-lockfile` / `npm ci`), so a release never resolves new dependency versions.

### Token on the command line (`cli.md`)

Command-line arguments are readable by every local user (`ps`, `/proc/<pid>/cmdline`) and land in shell history; the environment is readable only by the same user and root.

- **`--token` is removed.** The CLI never takes the token from argv. Removing it now is free (nothing is released); after `0.1.0` it would be a breaking change. `cli.md` (flag table, environment fallbacks, exit-code `2` list, `Arguments_spec.js` row in `testing.md`) is updated accordingly; the body of #219 is not kept in sync (the specs are the source of truth).
- **Token sources**, in precedence order:
  1. `--token-file <path>`: the file's content, with one trailing newline (`\n` or `\r\n`) trimmed; no other whitespace is trimmed.
  2. `PIDGEY_TOKEN`.
  An explicit `--token-file` wins over `PIDGEY_TOKEN`, as `--url` wins over `PIDGEY_URL`.
- **`--token-file` errors** are usage errors (exit `2`): file missing or unreadable, or a token that is empty or whitespace-only after trimming. The message names the path, never the content.
- **Permissions.** On POSIX systems, a token file readable by group or others (`mode & 0o077`) prints a warning to stderr and the send continues; no check on Windows. A refusal (as `ssh` does for keys) was rejected because orchestrator-mounted secrets often carry broad modes.
- **Still never:** the token is never read from stdin, never prompted for, and never printed.
- **Rejected:** keeping `--token` with a stderr warning (noisy, unsilenceable, ignored); keeping it documented only; an interactive `/dev/tty` prompt (unfit for scripting, conflicts with "missing token → exit `2`"); a config file or keychain with `pidgey login` (a feature of its own, out of scope for `0.1.0`); a `PIDGEY_TOKEN_FILE` variable (not needed now; can be added later without a breaking change).
- **Tests (for #220 / testing.md):** precedence of `--token-file` over `PIDGEY_TOKEN`; trailing-newline trim; missing, unreadable and empty file → exit `2`; the permission warning; `--token` rejected as an unknown flag.

### Public OpenAPI route (`openapi.md`)

- **Stays public** (no token, no scope), as `openapi.md` already states. It reveals nothing beyond what the `POST /v1/messages` error envelope and the published npm package already reveal; requiring a token would protect nothing secret and break tooling.
- **Static committed file.** The `/v1` document is a file committed in the repository and served verbatim by the route; the CI breaking-change diff and the client's type generation read that same file. It is never generated at runtime from the router, so no deployment-specific data (hosts, organizations, build info) and no non-`/v1` route can get into it. Its exact location in the backend tree is left to the implementation sub-issue.
- **Content rules**, a spec rule enforced by the route's PHPUnit test:
  - only `/v1` paths: no admin, `/auth`, operational or other non-`/v1` routes;
  - no `servers` entry with a real host (omitted, or the relative `/`);
  - `info.version` is the **contract** version (for example `1.0.0`), never the Pidgey release version, so the route does not fingerprint the running build;
  - examples use placeholder tokens (`pdg_example…`) and `example.com` hosts only, with no internal names, emails or IDs;
  - the Bearer security scheme is described without any detail of how tokens are stored or hashed.
- **No cache headers**, following `docs/agents/api/conventions/format.md` (Cache headers) like `/health.json`: the client never fetches the document at runtime, and the existing cache-headers pending item of `api/pending.md` covers any later change. `Cache-Control: no-cache` and `max-age` + `ETag` were rejected (an exception to the conventions with no consumer).
- **Load and abuse.** The route reads a static file and touches no database; like the operational probes, it ignores credentials (an invalid Bearer is served normally). Rate limiting stays with the global pending item of `api/pending.md`; no route-specific limit.

### Spec changes in this issue

In this issue's change set:

- The `**Pending decision** (#225)` markers settled above are replaced by the decisions: `packaging.md` (supply chain), `cli.md` (token), `openapi.md` (public route).
- The four markers not settled here are re-pointed to `(#231)`: `library.md` (cleartext, connection reuse / worst-case duration), `errors-and-retry.md` (retained body), `cli.md` (stdin size).
- The hub's pending-decisions table (`docs/agents/specs/node-client.md`) narrows the #225 row to its three topics and gains a #231 row.
- The `security` agent reviews the three decisions above in the PR.

## Benefits

- The CLI can no longer leak the token through argv or shell history, and supports mounted secret files.
- The published tarballs contain only what consumers need, with a CI check and a hardened publishing credential.
- The public OpenAPI route cannot leak deployment data or non-`/v1` routes by construction, with test-enforced content rules.
- Implementation sub-issues (#228) start from settled specs instead of open markers.
