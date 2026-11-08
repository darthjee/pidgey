# SSRF, DNS rebinding and the new failure code

Decision 1. Specify the send-time host check and resolve-once connect.

- `email/send-flow.md`: before connecting, resolve `host` once (A and AAAA; an IP literal is
  used as is); if `SMTP_ALLOW_PRIVATE_HOSTS` is `false` and any resolved address is in a
  denied range (loopback, RFC 1918, `fc00::/7`, link-local `169.254.0.0/16` / `fe80::/10`,
  `100.64.0.0/10`, unspecified, multicast, reserved; IPv4-mapped IPv6 checked as IPv4),
  refuse with `SMTP_HOST_NOT_ALLOWED` without contacting the server. Otherwise connect to
  the checked IP (IPv4 preferred, no fallback in v1), keeping `host` as TLS peer name and
  SNI (IP-literal rules unchanged). Place the check with the other send-time checks, after
  `SMTP_INSECURE_CREDENTIALS` and before password decryption. A resolution failure keeps
  today's `SMTP_CONNECTION_FAILED`.
- `email/failures.md`: add `409 SMTP_HOST_NOT_ALLOWED` —
  `The SMTP host resolves to an address that is not allowed`, no `error.smtp`.
- `email/smtp-connections.md`: describe the host policy.
- `api/endpoints/pidgey-admin/smtp-connections.md` and `api/endpoints/sending.md` (if it
  lists send failures): add the case to the test-email and send failure tables; rewrite the
  SSRF known limitation (internal hosts blocked by default; the oracle remains for public
  hosts and ports, and fully when the setting is `true`).
- `api/pending.md`: remove the SSRF item.

## Files to Change
- `docs/agents/specs/email/send-flow.md`
- `docs/agents/specs/email/failures.md`
- `docs/agents/specs/email/smtp-connections.md`
- `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md`
- `docs/agents/specs/api/endpoints/sending.md`
- `docs/agents/specs/api/pending.md`
