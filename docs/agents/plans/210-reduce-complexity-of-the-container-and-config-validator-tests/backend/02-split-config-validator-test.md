# Add the ConfigValidator base test case and split ConfigValidatorTest

Create abstract `Pidgey\Tests\Support\ConfigValidatorTestCase extends TestCase` holding the key constants and `VARIABLES` (protected const), `$saved`, `setUp`/`tearDown` unchanged, the helpers `assertNoValueLeaks` and `assertNoOriginLeaks` (protected), and the shared providers `productionEnvironments` and `developmentEnvironments` (public static).

Move the tests verbatim into `source/tests/unit/Infra/ConfigValidator/` (namespace `Pidgey\Tests\Unit\Infra\ConfigValidator`), each provider staying with the test that uses it:

- `ConfigValidatorGeneralTest` (7): `testValidBaselinePasses`, `testFullyConfiguredEnvironmentPasses`, `testEmptyOptionalSettingsUseDefaults`, `testSingleRuleFails` (+`singleFailures`), `testMigrationUserAndPasswordAreSetTogether` (+`migrationUserPairings`), `testDbPortIsNotChecked`, `testSeveralFailuresAreAllReported`.
- `ConfigValidatorSecretKeyTest` (6): `testPreviousKeyFailuresAreReportedOnceEach`, `testDevelopmentKeyAsSecretKeyIsRefusedInProduction`, `testDevelopmentKeyAsPreviousKeyIsRefusedOnceInProduction`, `testDevelopmentKeyInBothSettingsIsReportedForEach`, `testDevelopmentKeyIsAllowedInDevelopmentAndTest`, `testDevelopmentKeyIsMatchedExactly`.
- `ConfigValidatorSmtpKeyTest` (8): `testPreviousSmtpKeyFailuresAreReportedOnceEach`, `testSmtpKeyCheckedAgainstAMissingSecretKeyOnlyReportsTheSecretKey`, `testPreviousSmtpKeysWithoutACurrentKeyOnlyReportTheMissingKey`, `testDevelopmentSmtpKeyIsRefusedInProduction`, `testDevelopmentSmtpKeyAsPreviousKeyIsRefusedOnceInProduction`, `testDevelopmentSmtpKeyIsAllowedInDevelopmentAndTest`, `testDevelopmentSmtpKeyIsTheSharedValue`, `testDevelopmentSmtpKeyIsMatchedExactly`.
- `ConfigValidatorSmtpEncryptionTest` (6): `testMissingSodiumExtensionFails`, `testLoadedSodiumExtensionPasses`, `testDefaultsToTheRunningSodiumExtension`, `testSmtpEncryptionValidationPassesWithoutTheOtherSettings`, `testSmtpEncryptionValidationRunsTheSmtpKeyAndSodiumRules`, `testSmtpEncryptionValidationReportsAMissingKey`.
- `ConfigValidatorLogAndOriginsTest` (6): log level valid/invalid, allowed origins valid/invalid/http/once-each, with their providers.
- `ConfigValidatorSmtpSettingsTest` (10): `testSmtpDefaultsPass`, `testSmtpTestSettingsDefaultsAndValidValuesPass`, `testValidSmtpSettingsPass`, timeouts (2), CA file (2), EHLO name (2), `testSmtpTransportSettingsAreNotPartOfTheSmtpEncryptionValidation`, with their providers. If this class reaches CCN ≥ 20, move the CA file and EHLO name tests into `ConfigValidatorSmtpIdentityTest`.

Totals: 43 test methods (unchanged). Delete `source/tests/unit/Infra/ConfigValidatorTest.php`.

The SMTP-key, sodium and the original `validateSmtpEncryption` tests appeared in the issue as one "SMTP key" class; they are split in two here so each stays under CCN 20.

## Files to Change
- `source/tests/support/ConfigValidatorTestCase.php` — new abstract base.
- `source/tests/unit/Infra/ConfigValidator/ConfigValidator{General,SecretKey,SmtpKey,SmtpEncryption,LogAndOrigins,SmtpSettings}Test.php` — new.
- `source/tests/unit/Infra/ConfigValidatorTest.php` — deleted.
