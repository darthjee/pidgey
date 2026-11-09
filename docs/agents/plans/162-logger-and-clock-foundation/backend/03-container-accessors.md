# Container clock() and logger()

Extend `Pidgey\Container`:

- **Constructor:** append `?Clock $clock = null` and `?LoggerInterface $logger = null`
  after `$wallClock`. Never reorder: about 50 existing positional calls rely on
  `(connectionFactory, wallClock)`.
- **`clock(): Clock`:** returns `$this->clock ??= new SystemClock()`.
- **`logger(): LoggerInterface`:** returns `$this->logger ??= LoggerFactory::fromEnv()`.
  It is built on first use, so a request that never logs never builds a logger.
- Update the constructor docblock in the same style as `$wallClock`'s, and say that new
  callers pass these arguments by name.

**Tests (`tests/unit/ContainerTest.php`):**

- **`clock()`:** defaults to `SystemClock`, is memoized, and returns the injected instance
  when one is given (`new Container(clock: $clock)`).
- **`logger()`:** is memoized, returns the injected instance (`new Container(logger: $logger)`),
  and by default is a Monolog `Logger` on channel `pidgey` whose level follows `LOG_LEVEL`
  (set and restored through `putenv`).
- Add `#[UsesClass]` / `#[CoversClass]` attributes for `SystemClock` and `LoggerFactory` as
  the file's existing attributes require.

## Files to Change

- `source/lib/Container.php`: two constructor parameters, two accessors, `use` lines.
- `source/tests/unit/ContainerTest.php`: the cases above.
