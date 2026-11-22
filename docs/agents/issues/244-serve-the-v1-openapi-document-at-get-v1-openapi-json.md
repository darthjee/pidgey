# Issue: Serve the /v1 OpenAPI document at GET /v1/openapi.json

## Description

- Parent epic: #219 (wave 2, created by #228). Spec: `docs/agents/specs/node-client/openapi.md`.
- Owner: `backend` (the `security` agent reviews the public exception).
- Depended on #237 (`POST /v1/messages`), now merged (fe8cdf2). #237 guards `/v1` per route (`ApiTokenAuth`, `RequireSendScope` in `source/config/routes/v1.php`), with no prefix-wide gate. Blocks #245 and #250.

## Problem

The Node client derives its types and contract tests from a machine-readable description of `/v1`, and the CI diff of #245 needs a document to diff. Pidgey has no OpenAPI document and no route serving one.

## Expected Behavior

- `GET /v1/openapi.json` answers the OpenAPI 3.1 document of `/v1` without authentication, and `HEAD` answers the same headers.
- The document matches `docs/agents/api/endpoints/sending.md` and `docs/agents/api/conventions/errors.md`.
- Every point of "Points to reconcile" is settled in `docs/agents/api/`, and the section can be marked done in the spec.
- No other backend behaviour changes.

## Solution

Implement `docs/agents/specs/node-client/openapi.md`, sections Document and `GET /v1/openapi.json` (with Why public, Static committed file, Content rules, Caching, Load and abuse):

1. Write a static OpenAPI 3.1 document of `/v1` at `source/openapi/v1.json` (JSON, so it is served verbatim; never under `source/public/`, which the web server would serve directly): `POST /v1/messages` with its request body, `200` body and every documented error status, the `Idempotency-Key` request header, the `Idempotent-Replayed` response header, the error envelope with `error.details` and `error.smtp`, and the Bearer security scheme with the `send` scope. No admin API.
2. Serve it on `GET` and `HEAD /v1/openapi.json`: public, `200`, `Content-Type: application/json`, no cache headers. Other methods get the routing `404 NOT_FOUND`.
   - One handler serves both routes: `HEAD` sends the same status and headers as `GET` with an empty body. `ResponseSender` and the router are not changed.
   - "No CORS headers" means no CORS of its own: the route behaves like the rest of `/v1`, so the global `Cors` step still answers a trusted origin as usual. The spec and the `api/` doc get that wording.
3. Settle the section **Points to reconcile with the as-built docs**:
   - record the `.json` suffix as a documented exception in `docs/agents/api/conventions/format.md` and `docs/agents/api/endpoints/sending.md`;
   - record the public exception in `docs/agents/api/authentication/api-tokens.md`. In the code it is simply a route registered in `source/config/routes/v1.php` without `ApiTokenAuth`: `GET` and `HEAD` on **exactly** `/v1/openapi.json`, so no other `/v1` path or method becomes public; a Bearer header, valid or not, is ignored and the document served;
   - add the explicit `HEAD` route (the router never falls back from `HEAD` to `GET`, `docs/agents/request-handler/routing.md`);
   - include the body-parser statuses (`400`, `413`, `415`). The routing `404` is not listed on each operation: one sentence in `info.description` says any unmatched path or method answers `404 NOT_FOUND` with the error envelope. Each operation lists only its own statuses (`400`, `401`, `403`, `409`, `413`, `415`, `422` and the SMTP ones).
4. Document the route in `docs/agents/api/`, and state the `/v1` additive-only rule (section `/v1` is additive-only) there and in the document.
5. PHPUnit specs: `GET` and `HEAD` answer `200` with and without a Bearer (valid or not); no other method or path becomes public (`/v1/openapi.json/`, `/v1/openapi.jsonx`, `POST /v1/openapi.json` still get the gate or the `404`); the served document matches the committed file.
   - Validity: add `opis/json-schema` as a dev dependency, commit the official OpenAPI 3.1 meta-schema as a test fixture, and validate `source/openapi/v1.json` against it.
   - The same test enforces the content rules: only `/v1` paths, no real `servers` host, the contract `info.version`, placeholder examples only.
   - `HEAD` answers an empty body with the same headers as `GET`.
