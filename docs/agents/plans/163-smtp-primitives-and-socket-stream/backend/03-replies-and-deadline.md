# Deadline, Reply, ReplyParser and EhloExtensions

Implement the pure protocol primitives per the shared contracts.

- `Deadline` on `Pidgey\Infra\Clock`: `start()`, `remaining()` clamped at 0, `expired()`,
  `within()` (min of parent expiry and `now + seconds`). Floats throughout.
- `Reply`, `MalformedReply`.
- `ReplyParser::read()`:
  - reads lines through `SmtpStream::readLine()` with the one `$budget` deadline for the
    whole reply (per reply, not per line — slowloris);
  - strips a trailing CRLF or bare LF; a line without LF (too long or closed mid-line) is
    malformed; a content length above 2048 bytes is malformed;
  - an empty line, a line not matching three digits followed by `-`, space or end, a code
    outside 200–599, mixed codes, more than 100 lines → `MalformedReply`;
  - `NNN` alone and `NNN-` with empty text are valid;
  - a `StreamFailure` (`Closed`, `Timeout`) on the **first** line propagates; on a later
    line, `Closed` becomes `MalformedReply` and `Timeout` propagates;
  - raw bytes (NUL, stray CR, non-ASCII) are kept untouched.
- `Reply::text()`: join the line texts with one space; when every line starts with the same
  `X.Y.Z` enhanced status code and `X` equals the reply code's first digit, keep it once at
  the front.
- `EhloExtensions::fromReply()`: skip the first line; split each line into keyword and
  parameters; keywords case-insensitive; `AUTH` and `AUTH=` lines merged into one
  upper-case, de-duplicated mechanism list.

Tests (unit, table-driven with data providers, using a small in-test stub or
`FakeSmtpStream` once step 04 lands — order the commits so the parser tests use a minimal
`SmtpStream` stub here):

- `DeadlineTest` with `FrozenClock`: remaining, clamping, `within()` shorter and longer
  than the parent.
- `ReplyParserTest`: one row per rule of `send-flow.md#replies` and edge cases 4–6.
- `ReplyTest` (`text()` rules, the Gmail three-line example), `EhloExtensionsTest`.

## Files to Change

- `source/lib/Smtp/Deadline.php`, `Reply.php`, `ReplyParser.php`, `EhloExtensions.php`,
  `MalformedReply.php` — new.
- `source/tests/unit/Smtp/DeadlineTest.php`, `ReplyParserTest.php`, `ReplyTest.php`,
  `EhloExtensionsTest.php` — new.
