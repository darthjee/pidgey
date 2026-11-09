# SocketStream and its integration tests

Implement `Pidgey\Smtp\SocketStream` per the contract.

- `open()`: `stream_socket_client("tcp://$address:$port")` (IPv6 wrapped in brackets) with
  `$deadline->remaining()` as the connect timeout and the `TlsContext` options on the
  context; connect errors (refused, unreachable, timeout before TCP) → `Connect`. Then
  `stream_set_blocking(false)`; for implicit TLS run the handshake.
- Handshake (`ssl` and `upgradeToTls()`): loop `stream_socket_enable_crypto(true, method)`
  with `stream_select` until success, failure or deadline; failure → `Tls` with the last
  `openssl_error_string()` reasons as `reason()`; deadline → `Timeout`.
- `upgradeToTls()`: before the handshake, `UnexpectedData` when
  `stream_get_meta_data()['unread_bytes'] > 0` or a non-blocking `fread` returns bytes.
- `readLine()`: buffered reads with `stream_select` bounded by `$deadline->remaining()`;
  returns at the first LF or at `MAX_LINE_BYTES + 2` bytes; `Closed` on EOF before any byte
  of the line; a partial line on EOF is returned as is (the parser treats it as malformed);
  `Timeout` when the deadline is reached (immediately, without I/O, when it already expired).
- `writeLine()`: append CRLF, loop `fwrite` until every byte is written, `stream_select` for
  writability; `Timeout` / `Closed` as above.
- `localAddress()`: `stream_socket_get_name($socket, false)`, the IP part only.
- `close()`: idempotent `fclose`, no TLS shutdown handshake.

Tests (`tests/integration/Smtp/SocketStreamTest.php`):

- Fake server: plain greeting read; `ssl` with `server` (host `localhost`, connect
  `127.0.0.1`); IP-literal peer `127.0.0.1` succeeds via the IP SAN; `wrong-name` and
  `untrusted` → `Tls` with a reason; `STARTTLS` upgrade; `inject` → `UnexpectedData`;
  `hang` → `Timeout` within bounds (0.3 s); `drip` slowloris with a 0.3 s reply budget →
  `Timeout`; `close` before the greeting → `Closed`; a closed port → `Connect`; an overlong
  line returns without LF.
- Mailpit (`Pidgey\Tests\Support\Mailpit`): resolve the instance host to an IP in the test,
  then `plain` open + greeting, `starttls` upgrade with `SMTP_CA_FILE`, `ssl` open; each
  verified, never disabled.
- `tests/unit/Smtp/NoDisabledVerificationTest.php`: scans `source/lib` and `source/tests`
  (excluding this file) for `'verify_peer' => false`, `'verify_peer_name' => false` and
  `allow_self_signed`; fails naming the file.

## Files to Change

- `source/lib/Smtp/SocketStream.php` — new.
- `source/tests/integration/Smtp/SocketStreamTest.php` — new.
- `source/tests/unit/Smtp/NoDisabledVerificationTest.php` — new.
