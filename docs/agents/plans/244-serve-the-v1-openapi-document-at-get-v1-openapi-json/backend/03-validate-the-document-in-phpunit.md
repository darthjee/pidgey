# Validate the document in PHPUnit

Add `opis/json-schema` to `require-dev`, through the compose `tests` service so `composer.lock` is updated. Commit the official OpenAPI 3.1 meta-schema (the `https://spec.openapis.org/oas/3.1/schema/...` JSON Schema, with its source URL and date in a README or comment next to it) as a test fixture.

Add a unit test that:
- validates `source/openapi/v1.json` against the meta-schema;
- enforces the content rules: every path starts with `/v1/`; `servers` is absent or only `/`; `info.version` is `1.0.0`; any example token starts with `pdg_example` and any host or email is under `example.com`; the security scheme description says nothing about storage or hashing (no `sha`, `hash`, `bcrypt`);
- checks the served body equals the committed file byte for byte, if step 02's integration test doesn't already do so.

## Files to Change
- `source/composer.json`, `source/composer.lock` — add `opis/json-schema` (dev).
- `source/tests/fixtures/openapi/3.1-schema.json` — the official meta-schema.
- `source/tests/unit/OpenApi/V1DocumentTest.php` — new: validity and content rules.
