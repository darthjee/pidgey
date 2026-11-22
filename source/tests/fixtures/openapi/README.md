# OpenAPI 3.1 and JSON Schema 2020-12 meta-schemas

Committed unchanged, so the tests never need the network. Fetched on 2026-11-22.

| File | Source |
| --- | --- |
| `3.1-schema.json` | `https://spec.openapis.org/oas/3.1/schema/2022-10-07`, the official JSON Schema of OpenAPI 3.1 documents ("without schema validation") |
| `2020-12-schema.json` | `https://json-schema.org/draft/2020-12/schema` |
| `2020-12-meta-<name>.json` | `https://json-schema.org/draft/2020-12/meta/<name>` (`core`, `applicator`, `unevaluated`, `validation`, `meta-data`, `format-annotation`, `content`), the vocabularies of the 2020-12 meta-schema |

`tests/unit/OpenApi/V1DocumentTest.php` validates `source/openapi/v1.json` against them.
`3.1-schema.json` reaches every Schema Object through `{"$dynamicRef": "#meta"}`, which
`opis/json-schema` does not resolve to the OpenAPI schema dialect; the test therefore loads that
file with those references replaced by `true` (any Schema Object) and validates each Schema Object
of the document separately against the JSON Schema 2020-12 meta-schema.
