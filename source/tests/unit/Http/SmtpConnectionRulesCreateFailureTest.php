<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Http\FieldRules;
use Pidgey\Http\HostName;
use Pidgey\Http\HttpException;
use Pidgey\Http\IntegerRules;
use Pidgey\Http\SmtpConnectionChange;
use Pidgey\Http\SmtpConnectionRules;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Http\Validator;
use Pidgey\Model\SmtpConnection;
use Pidgey\Tests\Support\SmtpConnectionRulesTestCase;

#[CoversClass(SmtpConnectionRules::class)]
#[CoversClass(SmtpConnectionChange::class)]
#[UsesClass(FieldRules::class)]
#[UsesClass(HostName::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(IntegerRules::class)]
#[UsesClass(SmtpConnection::class)]
#[UsesClass(ValidationFailedException::class)]
#[UsesClass(Validator::class)]
final class SmtpConnectionRulesCreateFailureTest extends SmtpConnectionRulesTestCase
{
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
}
