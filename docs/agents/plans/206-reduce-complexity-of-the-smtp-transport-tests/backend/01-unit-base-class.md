# Unit base class

Create `abstract class SmtpTransportTestCase extends TestCase` in `source/tests/support/SmtpTransportTestCase.php` (namespace `Pidgey\Tests\Support`) and move into it, from the unit `SmtpTransportTest`:

- The constants `CANARY`, `USERNAME`, `HOST`, `CA_FILE`, `EHLO`, `MESSAGE`, as `protected const`.
- The `FrozenClock $clock` property (`protected`) and `setUp()` that builds `new FrozenClock(1000.0)`.
- Every private helper, changed to `protected`, bodies unchanged: `stream`, `happyStream`, `greet`, `ehlo`, `login`, `envelopeAndData`, `upTo`, `quit`, `target`, `settings`, `envelope`, `credentials`, `plainPayload`, `send`, `sendFailure`, `failure`, `assertNoCanary`, `leaks`.

The base class carries no test methods and no PHPUnit attributes. Keep its own CCN below 20. If it does not fit, move the canary helpers (`credentials`, `plainPayload`, `assertNoCanary`, `leaks`) into a trait `source/tests/support/SmtpTransportCanaryHelpers.php` that the base uses.

## Files to Change
- `source/tests/support/SmtpTransportTestCase.php` — new abstract base (constants, clock setup, dialogue/send/canary helpers).
- `source/tests/support/SmtpTransportCanaryHelpers.php` — only if needed to keep the base below CCN 20.
