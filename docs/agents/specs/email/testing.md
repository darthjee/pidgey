# Email Testing

Purpose: define how email sending is tested.

- Unit tests cover MIME building and SMTP reply → error code mapping with a fake transport.
- Integration tests send real email through the `mailpit` service (SMTP on port 1025)
  and assert on delivered messages using Mailpit's HTTP API (recipient, subject, bodies).
- Failure paths are tested by pointing connections at unreachable hosts/ports and by
  using wrong credentials where Mailpit is configured to require auth.
- Mailpit's mailbox is cleared before each test that inspects it.

See [testing](../testing.md).
