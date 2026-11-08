# MIME message

Write Decision 13 into the message scope, so `POST /v1/messages` reuses it later.

- `email/message-scope.md`: a "Message format" section with the header set and order,
  `Message-ID` on the `fromAddress` domain, RFC 2047 subject encoding, bodies always
  quoted-printable with CRLF normalization, the `multipart/alternative` layout and
  boundary, headers not added; the `subject` control-character validation rule; ASCII-only
  addresses, no `SMTPUTF8` (Decision 6).

## Files to Change
- `docs/agents/specs/email/message-scope.md` — message format and `subject` rule.
