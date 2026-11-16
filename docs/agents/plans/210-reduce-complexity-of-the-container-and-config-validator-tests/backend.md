# Backend Plan: Reduce complexity of the container and config validator tests

Main plan: [plan.md](plan.md)

## Overview
Same pattern as #206–#209 (see `source/tests/support/SessionsTestCase.php`, `UserRepositoryTestCase.php`, `SchemaTestCase.php`): move shared constants, environment save/restore and helpers into an abstract base class per subject, then move each test into a class focused on one concern. Delete each original file once empty. Per the user's choice, ContainerTest's 8 near-identical repository tests become one provider-driven test.

## Context
- `ContainerTest` (`Pidgey\Tests\Unit`, extends `TestCase`): 53 tests; `CoversClass(Container::class)` plus 37 `UsesClass` attributes; constants `VARIABLES` (21 env names), `SECRET_KEY`, `SMTP_KEY`, `PREVIOUS_SMTP_KEY`; state `$saved`; `setUp` saves every variable then sets/clears them; `tearDown` restores; helpers `unusedConnections`, `handlerLevel`, `fixedClock` (anonymous `WallClock` returning a fixed timestamp).
- `ConfigValidatorTest` (`Pidgey\Tests\Unit\Infra`, extends `TestCase`): 43 tests; `CoversClass(ConfigValidator::class)` plus `UsesClass` for `AppPath`, `EncryptionKeys`, `Env`, `LoggerFactory`, `OriginParser`; constants `VALID_KEY`, `OTHER_KEY`, `SMTP_KEY`, `PREVIOUS_SMTP_KEY`, `OLDER_SMTP_KEY`, `VARIABLES` (36 env names); `setUp` saves and clears every variable, then sets the valid baseline (`SECRET_KEY`, `SMTP_ENCRYPTION_KEY`, `DB_HOST`, `DB_NAME`, `DB_USER`); `tearDown` restores; helpers `assertNoOriginLeaks`, `assertNoValueLeaks`; providers `productionEnvironments` and `developmentEnvironments` are shared by the secret-key and SMTP-key tests.
- `source/tests/support/FrozenWallClock` exists: `new FrozenWallClock(int|DateTimeImmutable $at)`, fixed until `advance()`. It replaces `fixedClock`.
- Support classes autoload as `Pidgey\Tests\Support\` from `source/tests/support/` (PSR-4, autoload-dev).

## Steps

- [01 — Add the Container base test case and split ContainerTest](backend/01-split-container-test.md)
- [02 — Add the ConfigValidator base test case and split ConfigValidatorTest](backend/02-split-config-validator-test.md)
- [03 — Verify complexity, tests and lint](backend/03-verify.md)

## CI Checks
- `source`: `make tests` (runs `composer lint && composer tests` in docker; integration needs MySQL and Mailpit).
- Complexity: `darthjee/tingle_php_complexity:0.9.0` against `source/tests`.

## Notes
- Moving tests must not change assertions, provider rows or test method names (the 8 folded repository tests excepted: their assertions move into one method, their differences into provider rows).
- Each new concrete class repeats the `CoversClass`/`UsesClass` attributes it needs from its original (PHPUnit does not inherit attributes from the abstract base). When in doubt, keep the full original set; PHPUnit's `beStrictAboutCoverageMetadata`, if enabled, must stay green.
- Private constants/helpers referenced from subclasses become `protected` (static ones `protected static`). Providers are `public static`; shared providers live in the base and are referenced by name from subclasses.
- If a class still reaches CCN ≥ 20, split it further along the same concern lines; never drop or merge tests.
