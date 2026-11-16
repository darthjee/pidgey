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
final class SmtpConnectionRulesUpdateCredentialsTest extends SmtpConnectionRulesTestCase
{
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
}
