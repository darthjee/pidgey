# 01 — Message and MIME builder

Spec: `email/message-scope.md#message-format`.

- `Pidgey\Email\Message` (see the shared contracts).
- `Pidgey\Email\MimeBuilder(WallClock $clock, ?Closure $random = null)`. The `$random` hook
  returns 16 bytes; it defaults to `random_bytes` and is injected by tests for deterministic
  `Message-ID` and boundary values.
- `build(string $fromAddress, Message $message): string` returns the full message (headers,
  blank line, body), CRLF throughout, with no dot-stuffing and no final `.`.
- Headers, in this order:
  - `Date`: `D, d M Y H:i:s +0000`, UTC, from `WallClock`;
  - `From`;
  - `To`;
  - `Subject`;
  - `Message-ID`: `<32 hex @ fromAddress domain>`;
  - `MIME-Version: 1.0`;
  - `Content-Type`;
  - `Content-Transfer-Encoding` (single part only).
- Subject:
  - printable ASCII is kept as is, folded at 78 if needed;
  - otherwise RFC 2047 `=?UTF-8?B?...?=` words of at most 75 characters each, split only at
    UTF-8 character boundaries, joined with `CRLF SP`.
- Bodies: normalize `\r\n`, `\r` and `\n` to CRLF, then quoted-printable with lines of at
  most 76 characters. Use `quoted_printable_encode` after normalization, and verify it
  keeps CRLF hard breaks and soft breaks of at most 76; if it doesn't, hand-roll it.
- `multipart/alternative; boundary="=_pidgey_<32 hex>"`: the text part first, the html part
  second, each with its own `Content-Type` and `Content-Transfer-Encoding: quoted-printable`.
- Backstop: throw a `LogicException` when:
  - the subject has a control character (`\x00`–`\x1F`, `\x7F`);
  - an address has non-ASCII bytes, CR, LF or another control character.

## Files to Change

- `source/lib/Email/Message.php`: new.
- `source/lib/Email/MimeBuilder.php`: new.
- `source/tests/unit/Email/MessageTest.php`, `source/tests/unit/Email/MimeBuilderTest.php`:
  - header order and values with a frozen wall clock and fixed random bytes;
  - ASCII subject as is;
  - UTF-8 subject: encoded-word length of at most 75 and no split character, checked with a
    4-byte emoji at the boundary;
  - long subject folding;
  - QP line length of at most 76, and `=` / trailing space encoding;
  - lone LF and CR normalized;
  - text-only, html-only and both (order and boundary);
  - every `LogicException` backstop.
