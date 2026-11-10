<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\FieldRules;
use Pidgey\Http\HostName;
use Pidgey\Http\HttpException;
use Pidgey\Http\IntegerRules;
use Pidgey\Http\SmtpConnectionChange;
use Pidgey\Http\SmtpConnectionRules;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Http\Validator;
use Pidgey\Model\SmtpConnection;
use Pidgey\Model\SmtpEncryption;

#[CoversClass(SmtpConnectionRules::class)]
#[CoversClass(SmtpConnectionChange::class)]
#[UsesClass(FieldRules::class)]
#[UsesClass(HostName::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(IntegerRules::class)]
#[UsesClass(SmtpConnection::class)]
#[UsesClass(ValidationFailedException::class)]
#[UsesClass(Validator::class)]
final class SmtpConnectionRulesTest extends TestCase
{
    private const CANARY = 'canary-pw-9b1e';

    private const STORED_BYTES = 'stored-encrypted-bytes';

    private const HOST_MESSAGE = 'host must be a hostname or an IP address';

    public function testCreateReturnsTheValidatedFields(): void
    {
        $fields = SmtpConnectionRules::forCreate([
            'name' => '  Office relay ',
            'host' => ' smtp.example.com ',
            'port' => 587,
            'encryption' => ' tls ',
            'username' => ' relay-user ',
            'password' => ' ' . self::CANARY . ' ',
            'fromAddress' => ' noreply@example.com ',
            'active' => true,
            'organizationId' => 99,
        ]);

        $this->assertSame(
            [
                'name' => 'Office relay',
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => SmtpEncryption::Tls,
                'username' => 'relay-user',
                'password' => ' ' . self::CANARY . ' ',
                'fromAddress' => 'noreply@example.com',
            ],
            $fields,
        );
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function noAuthenticationProvider(): array
    {
        return [
            'absent' => [[]],
            'null' => [['username' => null, 'password' => null]],
            'empty' => [['username' => '', 'password' => '']],
            'whitespace username, empty password' => [['username' => '   ', 'password' => '']],
        ];
    }

    /**
     * @param array<string, mixed> $credentials
     */
    #[DataProvider('noAuthenticationProvider')]
    public function testCreateWithoutCredentials(array $credentials): void
    {
        $fields = SmtpConnectionRules::forCreate($this->createBody(['encryption' => 'none'] + $credentials));

        $this->assertNull($fields['username']);
        $this->assertNull($fields['password']);
        $this->assertSame(SmtpEncryption::None, $fields['encryption']);
    }

    public function testCreateRequiresEveryRequiredField(): void
    {
        $this->assertFailure(
            static fn () => SmtpConnectionRules::forCreate([]),
            [
                'name should not be empty',
                'host should not be empty',
                'port should not be empty',
                'encryption should not be empty',
                'fromAddress should not be empty',
            ],
        );
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function createFieldFailureProvider(): array
    {
        $long = str_repeat('a', 256);

        return [
            'empty name' => [['name' => ' '], ['name should not be empty']],
            'long name' => [['name' => $long], ['name must be shorter than or equal to 255 characters']],
            'name not a string' => [['name' => 5], ['name must be a string']],
            'port as a string' => [['port' => '587'], ['port must be an integer']],
            'port 0' => [['port' => 0], ['port must be between 1 and 65535']],
            'port 65536' => [['port' => 65536], ['port must be between 1 and 65535']],
            'port with a fraction' => [['port' => 587.5], ['port must be an integer']],
            'port as a float' => [['port' => 587.0], ['port must be an integer']],
            'port as a boolean' => [['port' => true], ['port must be an integer']],
            'unknown encryption' => [
                ['encryption' => 'starttls'],
                ['encryption must be one of the following values: none, tls, ssl'],
            ],
            'upper-case encryption' => [
                ['encryption' => 'TLS'],
                ['encryption must be one of the following values: none, tls, ssl'],
            ],
            'host with a space' => [['host' => 'smtp example.com'], [self::HOST_MESSAGE]],
            'host with CR LF' => [['host' => "smtp.example.com\r\nQUIT"], [self::HOST_MESSAGE]],
            'host with a tab' => [['host' => "smtp\t.example.com"], [self::HOST_MESSAGE]],
            'host with a NUL' => [['host' => "smtp\0.example.com"], [self::HOST_MESSAGE]],
            'Unicode host' => [['host' => 'smtp.exämple.de'], [self::HOST_MESSAGE]],
            'host label over 63' => [['host' => str_repeat('a', 64) . '.example.com'], [self::HOST_MESSAGE]],
            'host label leading hyphen' => [['host' => '-smtp.example.com'], [self::HOST_MESSAGE]],
            'host label trailing hyphen' => [['host' => 'smtp-.example.com'], [self::HOST_MESSAGE]],
            'host over 253' => [['host' => self::longHost(254)], [self::HOST_MESSAGE]],
            'host over 255' => [
                ['host' => self::longHost(256)],
                ['host must be shorter than or equal to 255 characters', self::HOST_MESSAGE],
            ],
            'host with a trailing dot' => [['host' => 'smtp.example.com.'], [self::HOST_MESSAGE]],
            'host with a zone id' => [['host' => 'fe80::1%eth0'], [self::HOST_MESSAGE]],
            'host 999.1.1.1' => [['host' => '999.1.1.1'], [self::HOST_MESSAGE]],
            'username with a control character' => [
                ['username' => "relay\x01user", 'password' => 'x'],
                ['username must not contain control characters'],
            ],
            'username with LF' => [
                ['username' => "relay\nuser", 'password' => 'x'],
                ['username must not contain control characters'],
            ],
            'username with DEL' => [
                ['username' => "relay\x7Fuser", 'password' => 'x'],
                ['username must not contain control characters'],
            ],
            'long username' => [
                ['username' => $long, 'password' => 'x'],
                ['username must be shorter than or equal to 255 characters'],
            ],
            'password with CR' => [
                ['username' => 'u', 'password' => "pw\rRSET"],
                ['password must not contain line breaks'],
            ],
            'password with LF' => [
                ['username' => 'u', 'password' => "pw\nRSET"],
                ['password must not contain line breaks'],
            ],
            'password with NUL' => [
                ['username' => 'u', 'password' => "pw\0x"],
                ['password must not contain a NUL byte'],
            ],
            'long password' => [
                ['username' => 'u', 'password' => str_repeat('é', 256)],
                ['password must be shorter than or equal to 255 characters'],
            ],
            'password not a string' => [['username' => 'u', 'password' => 12345], ['password must be a string']],
            'invalid fromAddress' => [['fromAddress' => 'nobody'], ['fromAddress must be an email']],
            'fromAddress with CR LF' => [
                ['fromAddress' => "noreply@example.com\r\nBcc: x@example.com"],
                ['fromAddress must be an email', 'fromAddress must not contain line breaks'],
            ],
            'fromAddress with U+0001 in a quoted local part' => [
                ['fromAddress' => "\"a\x01b\"@example.com"],
                ['fromAddress must not contain control characters'],
            ],
            'fromAddress with NUL in a quoted local part' => [
                ['fromAddress' => "\"a\0b\"@example.com"],
                ['fromAddress must be an email', 'fromAddress must not contain control characters'],
            ],
            'fromAddress with VT in a quoted local part' => [
                ['fromAddress' => "\"a\x0Bb\"@example.com"],
                ['fromAddress must not contain control characters'],
            ],
            'fromAddress with DEL in a quoted local part' => [
                ['fromAddress' => "\"a\x7Fb\"@example.com"],
                ['fromAddress must not contain control characters'],
            ],
            'long fromAddress' => [
                ['fromAddress' => str_repeat('a', 250) . '@example.com'],
                ['fromAddress must be an email', 'fromAddress must be shorter than or equal to 255 characters'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $override
     * @param list<string> $messages
     */
    #[DataProvider('createFieldFailureProvider')]
    public function testCreateFieldFailure(array $override, array $messages): void
    {
        $this->assertFailure(
            fn () => SmtpConnectionRules::forCreate($this->createBody($override)),
            $messages,
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function acceptedHostProvider(): array
    {
        return [
            'punycode' => ['smtp.xn--exmple-cua.de'],
            'IPv4' => ['192.0.2.25'],
            'bare IPv6' => ['2001:db8::25'],
            'bracketed IPv6' => ['[2001:db8::25]'],
            'single label' => ['mailpit'],
        ];
    }

    #[DataProvider('acceptedHostProvider')]
    public function testCreateAcceptsTheHost(string $host): void
    {
        $this->assertSame($host, SmtpConnectionRules::forCreate($this->createBody(['host' => $host]))['host']);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function unusualPairProvider(): array
    {
        return [
            'ssl on 587' => ['ssl', 587],
            'tls on 465' => ['tls', 465],
        ];
    }

    #[DataProvider('unusualPairProvider')]
    public function testCreateAcceptsUnusualPortEncryptionPairs(string $encryption, int $port): void
    {
        $fields = SmtpConnectionRules::forCreate($this->createBody(['encryption' => $encryption, 'port' => $port]));

        $this->assertSame($port, $fields['port']);
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function createCrossFieldProvider(): array
    {
        return [
            'only username' => [
                ['username' => 'relay-user'],
                [SmtpConnectionRules::PAIRING],
            ],
            'only password' => [
                ['password' => self::CANARY],
                [SmtpConnectionRules::PAIRING],
            ],
            'username with an empty password' => [
                ['username' => 'relay-user', 'password' => ''],
                [SmtpConnectionRules::PAIRING],
            ],
            'credentials with none' => [
                ['encryption' => 'none', 'username' => 'relay-user', 'password' => self::CANARY],
                [SmtpConnectionRules::ENCRYPTION_REQUIRED],
            ],
            'only username with none' => [
                ['encryption' => 'none', 'username' => 'relay-user'],
                [SmtpConnectionRules::PAIRING, SmtpConnectionRules::ENCRYPTION_REQUIRED],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $override
     * @param list<string> $messages
     */
    #[DataProvider('createCrossFieldProvider')]
    public function testCreateCrossFieldFailure(array $override, array $messages): void
    {
        $this->assertFailure(
            fn () => SmtpConnectionRules::forCreate($this->createBody($override)),
            $messages,
        );
    }

    public function testCreateCredentialsWithSsl(): void
    {
        $fields = SmtpConnectionRules::forCreate(
            $this->createBody(['encryption' => 'ssl', 'username' => 'relay-user', 'password' => self::CANARY]),
        );

        $this->assertSame('relay-user', $fields['username']);
        $this->assertSame(self::CANARY, $fields['password']);
    }

    public function testCrossFieldRulesRunOnlyOnceTheFieldRulesPass(): void
    {
        $this->assertFailure(
            fn () => SmtpConnectionRules::forCreate($this->createBody(['username' => 'u', 'port' => 0])),
            ['port must be between 1 and 65535'],
        );
    }

    public function testCreateFailureNeverContainsThePassword(): void
    {
        try {
            SmtpConnectionRules::forCreate(['password' => self::CANARY . "\n"]);
            $this->fail('Expected a ValidationFailedException');
        } catch (ValidationFailedException $error) {
            $this->assertStringNotContainsString(self::CANARY, $error->errorMessage());
            $this->assertStringNotContainsString(self::CANARY, implode(' ', $error->details() ?? []));
        }
    }

    public function testUpdateWithAnEmptyBodyChangesNothing(): void
    {
        $stored = $this->stored();

        $change = SmtpConnectionRules::forUpdate([], $stored);

        $this->assertFalse($change->hasChanges());
        $this->assertFalse($change->passwordChanged());
        $this->assertSame([], $change->changedFields);
        $this->assertNull($change->password());
        $this->assertEquals($stored, $change->merged);
    }

    public function testUpdateNullFieldsAreUnchanged(): void
    {
        $stored = $this->stored();
        $body = array_fill_keys(
            ['name', 'host', 'port', 'encryption', 'username', 'password', 'fromAddress'],
            null,
        );

        $change = SmtpConnectionRules::forUpdate($body, $stored);

        $this->assertSame([], $change->changedFields);
        $this->assertEquals($stored, $change->merged);
    }

    public function testUpdateIgnoresActiveAndUnknownFields(): void
    {
        $stored = $this->stored(active: false);

        $change = SmtpConnectionRules::forUpdate(['active' => true, 'id' => 99, 'organizationId' => 1], $stored);

        $this->assertSame([], $change->changedFields);
        $this->assertFalse($change->merged->active);
        $this->assertSame($stored->id, $change->merged->id);
        $this->assertSame($stored->organizationId, $change->merged->organizationId);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function requiredFieldProvider(): array
    {
        return [
            'name' => ['name'],
            'host' => ['host'],
            'encryption' => ['encryption'],
            'fromAddress' => ['fromAddress'],
        ];
    }

    #[DataProvider('requiredFieldProvider')]
    public function testUpdateCannotClearARequiredField(string $field): void
    {
        $this->assertFailure(
            fn () => SmtpConnectionRules::forUpdate([$field => '  '], $this->stored()),
            [$field . ' should not be empty'],
        );
    }

    public function testUpdateRejectsControlCharactersInTheFromAddress(): void
    {
        $this->assertFailure(
            fn () => SmtpConnectionRules::forUpdate(['fromAddress' => "\"a\x01b\"@example.com"], $this->stored()),
            ['fromAddress must not contain control characters'],
        );
    }

    public function testUpdateRunsTheFieldRules(): void
    {
        $this->assertFailure(
            fn () => SmtpConnectionRules::forUpdate(
                ['port' => '465', 'host' => 'smtp.exämple.de', 'username' => "a\tb", 'fromAddress' => "a\r\nb@x.io"],
                $this->stored(),
            ),
            [
                self::HOST_MESSAGE,
                'port must be an integer',
                'username must not contain control characters',
                'fromAddress must be an email',
                'fromAddress must not contain line breaks',
            ],
        );
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function changeWithCredentialsProvider(): array
    {
        return [
            'name' => [['name' => 'Renamed'], ['name']],
            'fromAddress' => [['fromAddress' => 'other@example.com'], ['fromAddress']],
            'username' => [['username' => 'other-user'], ['username']],
            'password rotation' => [['password' => self::CANARY], ['password']],
            'host with a password' => [
                ['host' => 'smtp.example.org', 'password' => self::CANARY],
                ['host', 'password'],
            ],
            'port with a password' => [['port' => 465, 'password' => self::CANARY], ['port', 'password']],
            'encryption with a password' => [
                ['encryption' => 'ssl', 'password' => self::CANARY],
                ['encryption', 'password'],
            ],
            'host and both credentials cleared' => [
                ['host' => 'smtp.example.org', 'username' => '', 'password' => ''],
                ['host', 'username', 'password'],
            ],
            'same host re-sent' => [['host' => 'smtp.example.com'], []],
            'same host with spaces' => [['host' => ' smtp.example.com '], []],
            'same values re-sent' => [
                [
                    'name' => 'Office relay',
                    'port' => 587,
                    'encryption' => 'tls',
                    'username' => 'relay-user',
                    'fromAddress' => 'noreply@example.com',
                ],
                [],
            ],
            'password with the same plain value' => [['password' => 'same-as-before'], ['password']],
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string> $changed
     */
    #[DataProvider('changeWithCredentialsProvider')]
    public function testUpdateAllowedOnAConnectionWithCredentials(array $body, array $changed): void
    {
        $change = SmtpConnectionRules::forUpdate($body, $this->stored());

        $this->assertSame($changed, $change->changedFields);
        $this->assertSame($changed !== [], $change->hasChanges());
        $this->assertSame(in_array('password', $changed, true), $change->passwordChanged());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function redirectionProvider(): array
    {
        return [
            'host' => [['host' => 'smtp.example.org']],
            'host case change' => [['host' => 'SMTP.example.com']],
            'port' => [['port' => 465]],
            'encryption' => [['encryption' => 'ssl']],
            'host with an empty password' => [['host' => 'smtp.example.org', 'password' => '', 'username' => '']],
            'host with a null password' => [['host' => 'evil.example.net', 'password' => null]],
            'host and username' => [['host' => 'evil.example.net', 'username' => 'other-user']],
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('redirectionProvider')]
    public function testUpdateRequiresThePasswordWhenTheServerChanges(array $body): void
    {
        $messages = $this->failure(fn () => SmtpConnectionRules::forUpdate($body, $this->stored()));

        if (($body['password'] ?? null) === '') {
            $this->assertSame([], $messages, 'clearing both credentials is allowed');

            return;
        }

        $this->assertContains(SmtpConnectionRules::PASSWORD_REQUIRED, $messages);
    }

    public function testUpdateChangingTheHostWithoutCredentialsNeedsNoPassword(): void
    {
        $change = SmtpConnectionRules::forUpdate(
            ['host' => 'smtp.example.org', 'port' => 25, 'encryption' => 'none'],
            $this->stored(username: null, password: null, encryption: SmtpEncryption::Tls),
        );

        $this->assertSame(['host', 'port', 'encryption'], $change->changedFields);
        $this->assertSame('smtp.example.org', $change->merged->host);
        $this->assertSame(25, $change->merged->port);
        $this->assertSame(SmtpEncryption::None, $change->merged->encryption);
    }

    public function testUpdateSettingNoneOnAConnectionWithCredentials(): void
    {
        $this->assertFailure(
            fn () => SmtpConnectionRules::forUpdate(['encryption' => 'none'], $this->stored()),
            [SmtpConnectionRules::ENCRYPTION_REQUIRED, SmtpConnectionRules::PASSWORD_REQUIRED],
        );
        $this->assertFailure(
            fn () => SmtpConnectionRules::forUpdate(
                ['encryption' => 'none', 'password' => self::CANARY],
                $this->stored(),
            ),
            [SmtpConnectionRules::ENCRYPTION_REQUIRED],
        );
    }

    public function testUpdateAddingCredentialsToANoneConnection(): void
    {
        $this->assertFailure(
            fn () => SmtpConnectionRules::forUpdate(
                ['username' => 'relay-user', 'password' => self::CANARY],
                $this->stored(username: null, password: null, encryption: SmtpEncryption::None),
            ),
            [SmtpConnectionRules::ENCRYPTION_REQUIRED],
        );
    }

    public function testUpdateAddingCredentialsWithTls(): void
    {
        $change = SmtpConnectionRules::forUpdate(
            ['username' => 'relay-user', 'password' => self::CANARY, 'encryption' => 'tls'],
            $this->stored(username: null, password: null, encryption: SmtpEncryption::None),
        );

        $this->assertSame(['encryption', 'username', 'password'], $change->changedFields);
        $this->assertSame(self::CANARY, $change->password());
        $this->assertSame('relay-user', $change->merged->username);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function mergedPairingProvider(): array
    {
        return [
            'clearing the username' => [['username' => '']],
            'clearing the password' => [['password' => '']],
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('mergedPairingProvider')]
    public function testUpdatePairingRunsOnTheMergedState(array $body): void
    {
        $this->assertFailure(
            fn () => SmtpConnectionRules::forUpdate($body, $this->stored()),
            [SmtpConnectionRules::PAIRING],
        );
    }

    public function testUpdateOnlyPasswordOnAConnectionWithoutUsername(): void
    {
        $this->assertFailure(
            fn () => SmtpConnectionRules::forUpdate(
                ['password' => self::CANARY],
                $this->stored(username: null, password: null),
            ),
            [SmtpConnectionRules::PAIRING],
        );
    }

    public function testUpdateClearingBothCredentials(): void
    {
        $change = SmtpConnectionRules::forUpdate(['username' => '', 'password' => ''], $this->stored());

        $this->assertSame(['username', 'password'], $change->changedFields);
        $this->assertTrue($change->passwordChanged());
        $this->assertNull($change->password());
        $this->assertNull($change->merged->username);
        $this->assertNull($change->merged->encryptedPassword);
    }

    public function testUpdateEmptyCredentialsOnAConnectionWithoutCredentialsAreNoChange(): void
    {
        $change = SmtpConnectionRules::forUpdate(
            ['username' => '', 'password' => ''],
            $this->stored(username: null, password: null),
        );

        $this->assertSame([], $change->changedFields);
        $this->assertFalse($change->passwordChanged());
    }

    public function testUpdateKeepsTheStoredPasswordBytesWithoutANewPassword(): void
    {
        $change = SmtpConnectionRules::forUpdate(['name' => 'Renamed', 'username' => 'other'], $this->stored());

        $this->assertSame(self::STORED_BYTES, $change->merged->encryptedPassword);
        $this->assertFalse($change->passwordChanged());
    }

    public function testUpdateOnALegacyRowWithCredentialsAndNoneFails(): void
    {
        $this->assertFailure(
            fn () => SmtpConnectionRules::forUpdate(
                ['name' => 'Renamed'],
                $this->stored(encryption: SmtpEncryption::None),
            ),
            [SmtpConnectionRules::ENCRYPTION_REQUIRED],
        );
    }

    public function testUpdateMergesTheTrimmedValues(): void
    {
        $change = SmtpConnectionRules::forUpdate(
            [
                'name' => ' Renamed ',
                'host' => ' [::1] ',
                'port' => 2525,
                'encryption' => 'ssl',
                'username' => ' other ',
                'password' => ' ' . self::CANARY . ' ',
                'fromAddress' => ' other@example.com ',
            ],
            $this->stored(),
        );

        $merged = $change->merged;
        $this->assertSame('Renamed', $merged->name);
        $this->assertSame('[::1]', $merged->host);
        $this->assertSame(2525, $merged->port);
        $this->assertSame(SmtpEncryption::Ssl, $merged->encryption);
        $this->assertSame('other', $merged->username);
        $this->assertSame('other@example.com', $merged->fromAddress);
        $this->assertSame(' ' . self::CANARY . ' ', $change->password());
        $this->assertSame(
            ['name', 'host', 'port', 'encryption', 'username', 'password', 'fromAddress'],
            $change->changedFields,
        );
    }

    public function testChangeDebugOutputHidesThePassword(): void
    {
        $change = SmtpConnectionRules::forUpdate(['password' => self::CANARY], $this->stored());

        $this->assertStringNotContainsString(self::CANARY, print_r($change, true));
        $this->assertStringNotContainsString(self::CANARY, var_export($change->__debugInfo(), true));
        $this->assertSame('[redacted]', $change->__debugInfo()['password']);
        $this->assertNull(
            SmtpConnectionRules::forUpdate([], $this->stored())->__debugInfo()['password'],
        );
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function createBody(array $override = []): array
    {
        return $override + [
            'name' => 'Office relay',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'fromAddress' => 'noreply@example.com',
        ];
    }

    private function stored(
        ?string $username = 'relay-user',
        ?string $password = self::STORED_BYTES,
        SmtpEncryption $encryption = SmtpEncryption::Tls,
        bool $active = true,
    ): SmtpConnection {
        $utc = new DateTimeZone('UTC');

        return new SmtpConnection(
            12,
            34,
            'Office relay',
            'smtp.example.com',
            587,
            $encryption,
            $username,
            $password,
            'noreply@example.com',
            $active,
            new DateTimeImmutable('2026-10-17 12:00:00', $utc),
            new DateTimeImmutable('2026-10-20 09:30:00', $utc),
        );
    }

    /**
     * @param callable(): mixed $call
     * @param list<string> $messages
     */
    private function assertFailure(callable $call, array $messages): void
    {
        $this->assertSame($messages, $this->failure($call));
    }

    /**
     * The validation details of the call, or an empty list when it passes.
     *
     * @param callable(): mixed $call
     *
     * @return list<string>
     */
    private function failure(callable $call): array
    {
        try {
            $call();
        } catch (ValidationFailedException $error) {
            return $error->details() ?? [];
        }

        return [];
    }

    private static function longHost(int $length): string
    {
        $labels = [];
        $remaining = $length;

        while ($remaining > 64) {
            $labels[] = str_repeat('a', 63);
            $remaining -= 64;
        }

        $labels[] = str_repeat('b', $remaining);

        return implode('.', $labels);
    }
}
