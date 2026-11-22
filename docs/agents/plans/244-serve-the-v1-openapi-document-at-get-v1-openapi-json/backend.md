# Backend Plan: Serve the /v1 OpenAPI document at GET /v1/openapi.json

Main plan: [plan.md](plan.md)

## Shared contracts

Produces everything listed in [plan.md](plan.md#shared-contracts): the file `source/openapi/v1.json` and the `GET`/`HEAD /v1/openapi.json` route served by `OpenApiHandler`.

## Steps

- [01 — Write the OpenAPI document](backend/01-write-the-openapi-document.md)
- [02 — Serve it on GET and HEAD](backend/02-serve-it-on-get-and-head.md)
- [03 — Validate the document in PHPUnit](backend/03-validate-the-document-in-phpunit.md)

## CI Checks

- `source/`: `docker compose run --rm tests composer lint` (CI job: lint)
- `source/`: `docker compose run --rm tests composer coverage` (CI job: tests)

## Notes

- Have `security` review the public exception before opening the PR. It should check that only exactly `/v1/openapi.json` with `GET`/`HEAD` skips the guards, and that the document leaks nothing beyond the public contract.
- `opis/json-schema` is `require-dev` only. CI already runs `composer install` after copying the pre-built vendor, so no image rebuild is needed for CI. Rebuilding the dev image is only needed locally.
- `build_backend` ships the whole `source/` tree, so `source/openapi/` reaches production with no infra change.
- Do not change `ResponseSender`, the router or `Cors`.
