# Issue: API request size limits

## Description
Decide the request size limits of the API: the body size of `POST /v1/messages`, the length of `subject`, `text` and `html`, the subject line-length and `=?` handling, and the error returned. Spun off from #231.

## Problem
`docs/agents/api/pending.md` lists size limits as a pending decision. The body parser already caps every request body at **1 MiB** and answers `413 HTTP_413` ([request.md](../request-handler/request-response/request.md#json-body), `Request::MAX_BODY_BYTES`), but this is not recorded as the decided limit of `POST /v1/messages`, its error table does not list `413`, and no field has a length cap. A printable-ASCII subject is sent as-is and folded only at whitespace, so an unbreakable run over ~989 characters breaks RFC 5322's 998-character line limit, and a subject containing `=?` can be misread as an encoded-word. The CLI spec caps stdin at 10 MiB, above the API's limit, and says the CLI cap follows the API limit.

## Expected Behavior
- The maximum body size of `POST /v1/messages` and where it is enforced are decided and documented.
- The maximum length of `subject`, `text` and `html`, and the subject line-length / `=?` handling, are decided and implemented.
- The error for each limit is recorded in the API docs.
- The CLI stdin cap is aligned with the API limit.
- The size-limits item is removed from `docs/agents/api/pending.md`.

## Solution
### Body size
- The existing **1 MiB** cap of the body parser is the decided limit for every route, `POST /v1/messages` included. No per-route limit.
- Enforced in the application only (`Content-Length` checked before reading, the read capped at limit + 1). No PHP, Apache or proxy change: the production shared host has no configurable proxy.
- An oversized body answers **`413 HTTP_413`** (the existing category code and reason phrase). No new named code.

### Field lengths
- `subject`: at most **255 characters** (UTF-8 characters, `maxLength(255)`) → `400 VALIDATION_FAILED` with `subject must be shorter than or equal to 255 characters`. With the `Subject: ` prefix no unbreakable run can reach 998 characters, so no line-length fallback is needed.
- A printable-ASCII subject containing `=?` is sent as RFC 2047 encoded-words, like a non-ASCII subject, so receivers never misread it as an encoded-word.
- `text` and `html`: no field cap; they are bounded by the 1 MiB body limit. An oversized message is still rejected by the SMTP server (`552` → `SMTP_REJECTED`).

### CLI
- The CLI stdin cap is lowered from 10 MiB to **1 MiB** (bytes before decoding) in the node-client specs. A body under 1 MiB whose JSON encoding exceeds 1 MiB gets the API's `413` (exit `1`). The CLI is unreleased, so this is not a breaking change.

### Changes
- `docs/agents/api/endpoints/sending.md`: add `400 BAD_REQUEST`, `413 HTTP_413` and `415` (body parser) to the error table; state the 1 MiB limit; drop size limits from its pending sentence.
- `docs/agents/email/message-scope.md`: the subject cap in Validation, the `=?` rule in Headers, and the Body note about the API body-size limit.
- `docs/agents/api/pending.md`: remove the size-limits item.
- `docs/agents/specs/node-client/` (`cli.md`, `testing.md`, `openapi.md` reconcile point on documented statuses): the 1 MiB stdin cap and the body-parser statuses.
- Backend (`backend`): `maxLength(255)` on `subject` in the send validation, and `=?` subjects routed through encoded-words in the message builder, with PHPUnit tests (exactly 255 accepted, 256 rejected; a `=?` subject encoded).

Owners: `product-owner` (docs and specs), `backend` (validation and subject encoding).

## Benefits
- Size limits are a documented contract that the Node client and the OpenAPI document can rely on.
- No subject can produce a header line over RFC 5322's limit or be misread as an encoded-word.
- The CLI rejects oversized input locally instead of uploading up to 10 MiB the API will refuse.
