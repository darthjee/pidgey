# Handler, route and tests

- `Pidgey\Http\Handler\SmtpConnectionTestHandler extends RequestHandler`. Its collaborators
  are the user and connection repositories, `TestEmailLimit`, `SendFlow` and `OperatorLog`,
  and it has a static `build(Container)` like `SmtpConnectionStateHandler`. `process()`:
  1. `PathId::parse($request, SmtpConnectionHandler::NOT_FOUND)`; the caller from
     `AuthenticatedUser`.
  2. Validate `to` with `Validator` / `FieldRules` (`optional()->trim()->notEmpty()->email()->maxLength(255)->noLineBreaks()`,
     matching the shared contract). Absent or `null` uses the caller's stored email. Otherwise
     resolve it with `findApprovedEmailInOrganization`; `null` → `ValidationFailedException`
     with `to must be the email of a user of the organization`.
  3. Load the connection snapshot once, in the organization, with a non-locking read
     (`404` when unknown).
  4. `$lease = $limit->acquire($organizationId)`. A `429` propagates.
  5. In `try`: `$outcome = $sendFlow->send($connection, TestEmail::message($recipient), SendActor::user($user->id))`,
     then `OperatorLog::record(SMTP_CONNECTION_TEST, …, outcome: 'sent' | failure code)`.
     On success answer `201` with `{status: 'sent', smtp: {code, message}}`; otherwise
     throw `SmtpFailureException::fromOutcome($outcome, $connection->port, $connection->encryption)`.
     In `finally`: `$limit->release($lease)`.
  6. An unexpected `Throwable` from the send propagates to the `500` path with no operator
     line. The lease is still released by `finally`.
  The handler never writes to `smtp_connections`.
- Route: `$router->post('/smtp-connections/:id/test.json', SmtpConnectionTestHandler::class, [Gate::class]);`
  in `source/config/routes/organization.php`, and the matching `RoutesTest` / `RouteGuards`
  entries.
- Integration tests (`source/tests/integration/Http/Handler/SmtpConnectionTestTest.php`),
  against Mailpit:
  - `201`: to the caller by default, to another approved user, with a typed address in a
    different case (the stored address receives it);
  - an inactive connection can be tested;
  - `400` for `""`, whitespace, a bad format, over 255 characters, CR/LF, an unknown
    address, a `pending` user, a `denied` user, and a user of another organization (all
    with the same message);
  - `404` for an unknown or foreign id;
  - `429`: the window, with its `Retry-After`; the lease (a row seeded with
    `busy_until` in the future);
  - the limit counts every attempt that passed lookup (`201`, `409`, `SMTP_*`) and not
    `400` / `404` / `429`;
  - `409 SMTP_INSECURE_CREDENTIALS` and `SMTP_PASSWORD_UNREADABLE`; `409 SMTP_HOST_NOT_ALLOWED`
    with `SMTP_ALLOW_PRIVATE_HOSTS=false` against `127.0.0.1`;
  - an `SMTP_*` failure (e.g. wrong credentials on Mailpit, or `FakeSmtpServer`) with
    `error.smtp`;
  - the lease is released after `201`, `409`, an `SMTP_*` failure and an injected
    exception;
  - the log lines: `smtp-send-attempt` then `smtp-connection-test`, and their contents. No
    line for `400` / `404` / `429`. The recipient appears only in `smtp-send-attempt`;
  - canary password: never in the response, the operator line or any exception message,
    plain or base64;
  - the connection row is unchanged after a test.

## Files to Change

- `source/lib/Http/Handler/SmtpConnectionTestHandler.php`: new.
- `source/config/routes/organization.php`: the route.
- `source/tests/unit/Config/RoutesTest.php` and `source/tests/support/RouteGuards.php`: the route.
- `source/tests/integration/Http/Handler/SmtpConnectionTestTest.php`: new.
