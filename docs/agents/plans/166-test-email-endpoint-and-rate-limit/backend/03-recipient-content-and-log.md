# Recipient, content and operator log

- `Pidgey\Repository\UserRepository::findApprovedEmailInOrganization(int $organizationId, string $email): ?string`
  returns the **stored** `users.email` of an `approved` user of the organization whose
  email matches (the column collation is case-insensitive), or `null` otherwise.
- `Pidgey\Email\TestEmail`: the fixed content, for example
  `TestEmail::message(string $to): Message`, with subject `Pidgey test email` and text
  `This is a test email sent by Pidgey to check an SMTP connection.` and no HTML. The
  `From:` header comes from the connection in `SendFlow`, as today.
- `Pidgey\Http\OperatorLog`:
  - add `SMTP_CONNECTION_TEST = 'smtp-connection-test'`;
  - add a trailing `?string $outcome = null` to `record()`, written as `outcome` only when
    set. Existing callers are unchanged.
- Tests:
  - `UserRepository`: approved match with a different case returns the stored value;
    `pending`, `denied` and other-organization users return `null`;
  - `TestEmail`: the exact subject and body;
  - `OperatorLog`: with and without `outcome`.

## Files to Change

- `source/lib/Repository/UserRepository.php`: the lookup.
- `source/lib/Email/TestEmail.php`: new.
- `source/lib/Http/OperatorLog.php`: the constant and `outcome`.
- `source/tests/integration/Repository/UserRepositoryTest.php`, `source/tests/unit/Email/TestEmailTest.php`
  and `source/tests/unit/Http/OperatorLogTest.php`: the cases.
