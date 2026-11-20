# Plan: API request size limits

Issue: [233-api-request-size-limits.md](../../issues/233-api-request-size-limits.md)

## Overview

Record the decided request size limits: the existing 1 MiB body cap (`413 HTTP_413`) becomes the documented limit of every route, `subject` is capped at 255 characters, `text`/`html` have no field cap, and a printable subject containing `=?` is sent as encoded-words. The CLI stdin cap drops from 10 MiB to 1 MiB in the node-client specs. `product-owner` updates the docs and specs; `backend` changes the subject encoding in `MimeBuilder`.

## Agents involved

- [product-owner](product-owner.md)
- [backend](backend.md)

## Shared contracts

- **Body limit:** 1 MiB (`Request::MAX_BODY_BYTES = 1048576`), every route, unchanged. Over the limit → `413`, code `HTTP_413`, message `Content Too Large`. No code change.
- **Subject cap:** `subject` at most **255** UTF-8 characters (`maxLength(255)`), message `subject must be shorter than or equal to 255 characters`, answered as `400 VALIDATION_FAILED`. `POST /v1/messages` has no handler yet: this is a spec rule its implementation applies. No backend code in this issue.
- **`=?` rule:** a subject that is printable ASCII but contains the two characters `=?` is encoded as RFC 2047 encoded-words (`=?UTF-8?B?…?=`), exactly like a non-ASCII subject. Every other printable-ASCII subject is unchanged (kept as-is, folded at 78).
- **`text` / `html`:** no field cap.
- **CLI stdin cap:** 1 MiB (1048576 bytes, counted before decoding); exactly 1 MiB accepted, one byte more exits `2`.
