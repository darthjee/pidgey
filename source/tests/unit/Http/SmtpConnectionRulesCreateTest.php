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
final class SmtpConnectionRulesCreateTest extends SmtpConnectionRulesTestCase
{
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

    public function testCreateCredentialsWithSsl(): void
    {
        $fields = SmtpConnectionRules::forCreate(
            $this->createBody(['encryption' => 'ssl', 'username' => 'relay-user', 'password' => self::CANARY]),
        );

        $this->assertSame('relay-user', $fields['username']);
        $this->assertSame(self::CANARY, $fields['password']);
    }
}
