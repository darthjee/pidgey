# Message Scope

Purpose: define which message features are supported in v1 and which are deferred.

## v1

| Field | Required | Notes |
| --- | --- | --- |
| `to` | yes | A single recipient address. |
| `subject` | yes | Plain text, UTF-8 (encoded as needed in headers); must not contain control characters (see [Validation](#validation)). |
| `text` | one of `text`/`html` | Plain-text body. |
| `html` | one of `text`/`html` | HTML body. When both are given, a `multipart/alternative` message is built. |

The `From:` header comes from the SMTP connection's `from_address`.

### Validation

- `to` and the connection's `fromAddress` are **ASCII-only** addresses, as in the v1 rule
  of [api/conventions/validation.md](../api/conventions/validation.md) (no IDN domain, no
  Unicode local part; see [Addresses](#addresses)).
- `subject` must not contain control characters (CR, LF and TAB included) →
  `subject must not contain control characters` (prevents header injection). The rule only
  affects requests, never stored data.

## Message format

The same rules build every message: the test email (`text` only) and `POST /v1/messages`.

### Addresses

- `fromAddress` and every recipient are ASCII-only. Pidgey never sends `SMTPUTF8` and
  never converts an address domain; the send flow relies on 7-bit addresses.
- Future, not v1: `SMTPUTF8` addresses.

### Headers

In this order:

| Header | Value |
| --- | --- |
| `Date` | RFC 5322 date from `Pidgey\Infra\Clock`, in UTC (`Sat, 07 Nov 2026 14:03:00 +0000`). |
| `From` | `fromAddress`, bare address (no display name in v1). |
| `To` | The recipient, bare address. |
| `Subject` | Printable ASCII as-is; otherwise RFC 2047 encoded-words (`=?UTF-8?B?…?=`), each at most 75 characters, never splitting a UTF-8 character; folded at 78 characters. |
| `Message-ID` | `<` 32 hex characters (`random_bytes(16)`) `@` the `fromAddress` domain `>`. |
| `MIME-Version` | `1.0`. |
| `Content-Type` / `Content-Transfer-Encoding` | See [Body](#body). |

Not added: `X-Mailer` / `User-Agent` (fingerprinting), `Reply-To`, `Sender`,
`Return-Path` (set by the receiver), DKIM signatures (the relay's job).

### Body

| Fields given | `Content-Type` |
| --- | --- |
| `text` only | `text/plain; charset=UTF-8`, `quoted-printable` |
| `html` only | `text/html; charset=UTF-8`, `quoted-printable` |
| both | `multipart/alternative; boundary="=_pidgey_<32 hex>"`: the text part first, the HTML part last (RFC 2046: the last is preferred) |

- Line endings are normalized to CRLF before encoding (a lone LF or CR becomes CRLF).
- Always `quoted-printable`, even for pure ASCII (one code path): the message is 7-bit,
  lines are at most 76 characters, and `8BITMIME` / `BODY=8BITMIME` is never used.
- The boundary cannot collide with a part: `=_` never occurs in quoted-printable output.
- Dot-stuffing and the final `CRLF.CRLF` belong to the SMTP transport, not to the message
  (see [send-flow.md](send-flow.md#envelope-and-data)).
- There is no `SIZE` parameter on `MAIL FROM`; an oversized message is rejected by the
  server (`552` → `SMTP_REJECTED`). An API body-size limit stays a pending item of
  `POST /v1/messages` ([api/pending.md](../api/pending.md)).

## Pending decisions (future scope)

- Multiple recipients, `cc`, `bcc`, and whether `to` accepts an array.
- `reply_to` and custom headers.
- Overriding `from` per request, and a `From` display name.
- **Attachments.**
- **Templates** (stored templates with variables).
