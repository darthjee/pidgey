# Synchronous Send Flow

Purpose: define, step by step, what happens when a send request is received.

Sending is **synchronous**: the HTTP request blocks until the SMTP transaction ends
and the response carries the outcome. There is no queue and no retry in v1.

1. `POST /v1/messages` arrives with a Bearer token (see [api](../api.md)).
2. Authenticate the token; require scope `send`; resolve the token's organization.
3. Validate the payload (see [message-scope.md](message-scope.md)); invalid → `422`.
4. Resolve the organization's **active** SMTP connection; none → error `no_smtp_connection`.
5. Open the connection (with `ssl` implicit TLS, or plain then `STARTTLS` for `tls`),
   `EHLO`, authenticate if credentials exist.
6. `MAIL FROM` (connection `from_address`), `RCPT TO`, `DATA` with the built MIME message,
   `QUIT`.
7. Return the outcome: success with the SMTP server's final reply, or a categorized
   failure (see [failures.md](failures.md)).
8. Log the attempt via monolog (organization, token id, recipient, outcome; never the body
   or credentials).

Timeouts for connect and each command are configurable with conservative defaults
so API requests cannot hang indefinitely.

**Pending decision:** whether send attempts are persisted (a `messages` log table).
