# Plan: Serve the /v1 OpenAPI document at GET /v1/openapi.json

Issue: [244-serve-the-v1-openapi-document-at-get-v1-openapi-json.md](../../issues/244-serve-the-v1-openapi-document-at-get-v1-openapi-json.md)

## Overview

`backend` commits a static OpenAPI 3.1 document of `/v1` at `source/openapi/v1.json` and serves it verbatim on `GET` and `HEAD /v1/openapi.json` through one public handler, registered in `source/config/routes/v1.php` without `ApiTokenAuth`. PHPUnit validates the file against the OpenAPI 3.1 meta-schema (`opis/json-schema`, dev only) and enforces the spec's content rules. `product-owner` records the route, the `.json` exception, the public exception and the additive-only rule in `docs/agents/api/`, and closes the spec's "Points to reconcile" section.

## Agents involved

- [backend](backend.md)
- [product-owner](product-owner.md)

## Shared contracts

- **File:** `source/openapi/v1.json`, JSON, `"openapi": "3.1.0"`, `info.version` `"1.0.0"` (the contract version, never the Pidgey release), no `servers` entry with a real host.
- **Paths:** only `/v1/messages` (`post`) and `/v1/openapi.json` (`get`, `head`, `security: []`). No admin, `/auth` or operational routes.
- **`POST /v1/messages` responses:** `200` (`{ status: "sent", smtp: { code, message } }`, header `Idempotent-Replayed`), `400`, `401`, `403`, `409`, `413`, `415`, `422`, plus the SMTP statuses of the `sending.md` error table, all with the error envelope (`error.code`, `error.message`, optional `error.details`, `error.smtp`). No per-operation `404`.
- **`info.description`** states (1) any unmatched path or method answers `404 NOT_FOUND` with the error envelope, and (2) `/v1` is additive-only.
- **Security scheme:** HTTP Bearer, with the `send` scope stated in its description; nothing about storage or hashing. Examples use `pdg_example…` tokens and `example.com` only.
- **Route:** `GET` and `HEAD /v1/openapi.json`, handler `Pidgey\Http\Handler\OpenApiHandler`, no per-route middleware. `200`, `Content-Type: application/json`, no cache headers. `HEAD` has the same status and headers and an empty body. A Bearer header, valid or not, is ignored. The global `Cors` step is unchanged ("no CORS of its own").
