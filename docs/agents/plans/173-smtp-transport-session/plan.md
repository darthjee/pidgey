# Plan: SMTP transport session

Issue: [173-smtp-transport-session.md](../../issues/173-smtp-transport-session.md)

## Overview

Build the SMTP session, `Pidgey\Smtp\SmtpTransport`, on top of the #163 primitives
(`SmtpStream` / `SocketStream`, `ReplyParser`, `EhloExtensions`, `Deadline`,
`SessionSettings`, `StreamFailure`, `MalformedReply`, `SecretString`). It runs the step
sequence of `email/send-flow.md#smtp-session` and returns the raw final reply, or throws a
classified `SmtpFailure` per `email/failures.md#classification-rules`. The transport-side
input types keep `Pidgey\Smtp\` free of `Pidgey\Model\`. The backend writes the code and the
tests; the product-owner ticks the `implement-email/transport.md` checklist.

## Agents involved

- [backend](backend.md)
- [product-owner](product-owner.md)

## Shared contracts

These are the names the backend delivers. The product-owner refers to them in the checklist
note, and #165 consumes them. All are in namespace `Pidgey\Smtp\`.

**Enums**

| Name | Cases |
| --- | --- |
| `TlsMode` | `Plain`, `StartTls`, `Implicit` |
| `SmtpStep` | `Connect`, `Greeting`, `Ehlo`, `StartTls`, `Auth`, `MailFrom`, `RcptTo`, `Data`, `EndOfData` |
| `FailureCode` (string-backed) | `SMTP_CONNECTION_FAILED`, `SMTP_TLS_FAILED`, `SMTP_AUTH_FAILED`, `SMTP_REJECTED`, `SMTP_TEMPORARY_FAILURE`, `SMTP_PROTOCOL_ERROR`, `SMTP_TIMEOUT` |

**Value objects and the result**

| Name | Kind | Fields |
| --- | --- | --- |
| `Target` | `final readonly` | `string $host`, `string $address` (the checked IP), `int $port`, `TlsMode $tlsMode` |
| `Credentials` | `final` | `string $username` and a `#[SensitiveParameter] SecretString $password` |
| `Envelope` | `final readonly` | `string $from`, `list<string> $recipients` (non-empty) |
| `SmtpResult` | `final readonly` | `Reply $reply`, the raw reply to the final `.` |

`Credentials` implements neither `__toString` nor `JsonSerializable`, and its `__debugInfo`
returns `['username' => …, 'password' => '[redacted]']`.

**`SmtpFailure`** is a `final` class extending `RuntimeException`, with:

- `failureCode(): FailureCode`;
- `step(): SmtpStep`;
- `reply(): ?Reply`, which is raw;
- `reason(): ?string`, the OpenSSL reason, TLS only.

Its message is `SMTP <step>: <code>` (e.g. `SMTP RcptTo: SMTP_REJECTED`), and its
`previous` is the `StreamFailure` / `MalformedReply` when there is one.

**The transport**

- `interface Transport` declares `send(Target $target, SessionSettings $settings,
  ?Credentials $credentials, Envelope $envelope, string $message): SmtpResult`. It throws
  `SmtpFailure`, and `$credentials` and `$message` are `#[SensitiveParameter]`.
- `final class SmtpTransport implements Transport` is built with
  `new SmtpTransport(Clock $clock, ?Closure $streamFactory = null)`. The factory has the
  shape `Closure(): SmtpStream` and defaults to `fn () => new SocketStream()`. It is called
  once per `send()`.
- `Pidgey\Container::smtpTransport(): Transport` is memoized and built from
  `$this->clock()`.
