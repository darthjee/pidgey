# Node Client (spec)

Spec of a feature **not built yet**. Parent epic: #219 ("Create Clients").

Pidgey exposes a JSON API authenticated by scoped API tokens, but ships no client. Every
consumer hand-rolls the request body of `POST /v1/messages`, the `Authorization: Bearer`
header, the decoding of the success body and of the error envelope, and the decision of
whether an `SMTP_*` failure may be retried. This spec defines:

- the Node.js library **`pidgey-client`** (folder `clients/nodejs/`), covering
  `POST /v1/messages` only;
- the command-line client **`pidgey-cli`** (folder `clients/cli/`, global command
  `pidgey`), a thin layer over the library;
- the **OpenAPI 3.1 document** of the `/v1` surface, served by the backend at
  `GET /v1/openapi.json`, the source of the library's types and contract tests.

The current API contract the client consumes is described as built in
[api/endpoints/sending.md](../api/endpoints/sending.md),
[api/conventions/errors.md](../api/conventions/errors.md) and
[email/failures.md](../email/failures.md). Where this spec and those docs disagree on current
behaviour, the as-built docs win.

## Scope

In scope:

- The library `pidgey-client` (`clients/nodejs/`), covering `POST /v1/messages` only.
- The CLI `pidgey-cli` (`clients/cli/`, command `pidgey`), with no HTTP or retry logic of
  its own.
- Publishing **both** packages to the public npm registry within the epic: the library
  first, then the CLI depending on a published library version.
- The `/v1` OpenAPI 3.1 document and its route `GET /v1/openapi.json`.
- The client docs, the `nodejs` and `cli` agents, the ownership updates, the
  `clients_checks` and `clients_integration` CI jobs and the `Makefile` targets.

Out of scope (non-goals of the epic):

- **Other languages.** Ruby, Python, PHP or any other client is a separate, future issue,
  living in its own folder under `clients/`.
- **Browser target.** No browser build, bundle or browser test run. The library only stays
  free of Node-only APIs beyond `fetch` and `AbortSignal`, so a later browser target is not
  blocked.
- **CLI Docker image or standalone binaries.** The CLI is distributed only as an npm package.
- **Admin API in the client.** No organization, user, API-token or SMTP-connection
  management: those routes are cookie-session only.
- **Future message fields.** Multiple recipients, `cc`/`bcc`, `reply_to`, custom headers,
  `from` override, attachments and templates (the pending items of
  [email/message-scope.md](../email/message-scope.md#pending-decisions-future-scope)). The
  client sends only the v1 fields; supporting a new API field is a client release.
- **Backend changes beyond the OpenAPI route.** `GET /v1/openapi.json` is the only backend
  addition; every existing endpoint, payload, response, error code and header stays
  unchanged.
- **OpenAPI for the admin API.** The document covers `/v1` only.
- **Idempotency.** Removing the client-to-Pidgey duplicate case with an `Idempotency-Key`
  is tracked separately in #221.
- **API request size limits.** Decided in #233: a 1 MiB request body limit and a 255-character
  `subject` cap ([api/endpoints/sending.md](../api/endpoints/sending.md#size-limits)). The CLI
  stdin cap equals the body limit ([node-client/cli.md](node-client/cli.md#stdin-size-cap)).

## Files

| File | Content |
| --- | --- |
| [library.md](node-client/library.md) | `PidgeyClient`, its options, `send(message, { signal })`, the v1 field whitelist, headers, success passthrough, redirects, abort, timeout, connections, the cleartext rule. |
| [errors-and-retry.md](node-client/errors-and-retry.md) | `PidgeyError`, client-local codes, `possiblySent`, the retry table by `error.code`, `retryAmbiguous`, backoff, the retained response body. |
| [cli.md](node-client/cli.md) | The `pidgey` command: flags, token sources (`--token-file`, `PIDGEY_TOKEN`), environment fallbacks, stdin and its size cap, exit codes, output. |
| [openapi.md](node-client/openapi.md) | The `/v1` OpenAPI document, `GET /v1/openapi.json`, generated types and `.d.ts`, contract tests, the additive-only rule and its CI diff. |
| [packaging.md](node-client/packaging.md) | Packages and names, stack, runtime dependencies, versioning and compatibility, publishing and supply chain, ownership. |
| [testing.md](node-client/testing.md) | Test approach, edge-case rules, spec files per package, cross-cutting checks, the extra checks, the shared scripts, `Makefile` targets and CI jobs. |

## Pending decisions

Every open item is marked `**Pending decision** (#<issue>)` in the file where the decision
will land. Each enhancement sub-issue replaces its own markers.

| Issue | Topic | Markers in |
| --- | --- | --- |
| #222 | How `pidgey-cli` depends on a published `pidgey-client` (caret, exact pin, lockstep). | [packaging.md](node-client/packaging.md), [cli.md](node-client/cli.md) |
| #223 | Publishing mechanism, credential and release procedure. | [packaging.md](node-client/packaging.md) |
| #226 | Node.js version and supported `engines` range. | [packaging.md](node-client/packaging.md) |

## Lifecycle

- From the merge of this spec on, these files are the source of truth for the epic; the
  body of #219 is not kept in sync. A decision that changes during implementation updates
  these files first (while they exist), then the as-built docs.
- The specs are **temporary**. Each implementation sub-issue writes the as-built docs in
  the same change set as its code: the area hub `docs/agents/clients.md`, indexing
  `docs/agents/clients/nodejs.md` and `docs/agents/clients/cli.md`. Once those cover every
  rule here, #229 deletes this hub and `docs/agents/specs/node-client/`, and removes the row
  from [specs.md](../specs.md).
- Examples use placeholder tokens (`pdg_example…`) and `example.com` hosts only.
