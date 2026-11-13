<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Smtp;

use Error;
use JsonSerializable;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\SecretString;
use Pidgey\Smtp\Credentials;
use Stringable;

#[CoversClass(Credentials::class)]
#[UsesClass(SecretString::class)]
final class CredentialsTest extends TestCase
{
    private const CANARY = 'canary-smtp-password-4b1e';

    public function testAccessorsReturnTheUsernameAndTheWrappedPassword(): void
    {
        $password = new SecretString(self::CANARY);
        $credentials = new Credentials('mailer', $password);

        $this->assertSame('mailer', $credentials->username());
        $this->assertSame($password, $credentials->password());
        $this->assertSame(self::CANARY, $credentials->password()->reveal());
    }

    public function testDebugInfoHidesThePassword(): void
    {
        $this->assertSame(
            ['username' => 'mailer', 'password' => '[redacted]'],
            $this->credentials()->__debugInfo(),
        );
    }

    public function testPrintRIsRedacted(): void
    {
        $output = print_r($this->credentials(), true);

        $this->assertStringContainsString('mailer', $output);
        $this->assertStringContainsString('[redacted]', $output);
        $this->assertStringNotContainsString(self::CANARY, $output);
    }

    public function testVarDumpIsRedacted(): void
    {
        ob_start();
        var_dump($this->credentials());
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('[redacted]', $output);
        $this->assertStringNotContainsString(self::CANARY, $output);
    }

    public function testSerializeThrows(): void
    {
        $this->expectException(LogicException::class);

        serialize($this->credentials());
    }

    public function testUnserializeThrows(): void
    {
        $this->expectException(LogicException::class);

        unserialize('O:' . strlen(Credentials::class) . ':"' . Credentials::class . '":0:{}');
    }

    public function testCloneThrows(): void
    {
        $credentials = $this->credentials();

        $this->expectException(LogicException::class);

        $copy = clone $credentials;
        $this->assertNotSame($credentials, $copy);
    }

    public function testStringCastThrows(): void
    {
        $credentials = $this->credentials();

        $this->expectException(Error::class);

        $this->castToString($credentials);
    }

    public function testIsNeitherStringableNorJsonSerializable(): void
    {
        $credentials = $this->credentials();

        $this->assertNotInstanceOf(Stringable::class, $credentials);
        $this->assertNotInstanceOf(JsonSerializable::class, $credentials);
        $this->assertSame('{}', json_encode($credentials));
    }

    private function credentials(): Credentials
    {
        return new Credentials('mailer', new SecretString(self::CANARY));
    }

    private function castToString(mixed $value): string
    {
        return (string) $value;
    }
}
