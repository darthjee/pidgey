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
use Pidgey\Model\SmtpEncryption;
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
final class SmtpConnectionRulesUpdateTest extends SmtpConnectionRulesTestCase
{
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

    public function testUpdateKeepsTheStoredPasswordBytesWithoutANewPassword(): void
    {
        $change = SmtpConnectionRules::forUpdate(['name' => 'Renamed', 'username' => 'other'], $this->stored());

        $this->assertSame(self::STORED_BYTES, $change->merged->encryptedPassword);
        $this->assertFalse($change->passwordChanged());
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
        $this->assertStringNotContainsString(self::STORED_BYTES, print_r($change, true));
        $this->assertNull(
            SmtpConnectionRules::forUpdate([], $this->stored())->__debugInfo()['password'],
        );
    }
}
