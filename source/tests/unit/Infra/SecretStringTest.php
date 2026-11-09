<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra;

use Error;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\Env;
use Pidgey\Infra\LoggerFactory;
use Pidgey\Infra\SecretString;
use Pidgey\Tests\Support\CapturedLog;

#[CoversClass(SecretString::class)]
#[UsesClass(LoggerFactory::class)]
#[UsesClass(Env::class)]
final class SecretStringTest extends TestCase
{
    private const CANARY = 'canary-secret-7f3a91';

    public function testRevealReturnsTheValue(): void
    {
        $this->assertSame(self::CANARY, (new SecretString(self::CANARY))->reveal());
    }

    public function testStringCastThrows(): void
    {
        $secret = new SecretString(self::CANARY);

        $this->expectException(Error::class);

        $this->castToString($secret);
    }

    public function testSerializeThrows(): void
    {
        $this->expectException(LogicException::class);

        serialize(new SecretString(self::CANARY));
    }

    public function testUnserializeThrows(): void
    {
        $this->expectException(LogicException::class);

        unserialize('O:' . strlen(SecretString::class) . ':"' . SecretString::class . '":1:{s:5:"value";s:3:"abc";}');
    }

    public function testCloneThrows(): void
    {
        $secret = new SecretString(self::CANARY);

        $this->expectException(LogicException::class);

        $copy = clone $secret;
        $this->assertNotSame($secret, $copy);
    }

    public function testPrintRIsRedacted(): void
    {
        $output = print_r(new SecretString(self::CANARY), true);

        $this->assertStringContainsString('[redacted]', $output);
        $this->assertStringNotContainsString(self::CANARY, $output);
    }

    public function testVarDumpIsRedacted(): void
    {
        ob_start();
        var_dump(new SecretString(self::CANARY));
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('[redacted]', $output);
        $this->assertStringNotContainsString(self::CANARY, $output);
    }

    public function testJsonEncodeGivesAnEmptyObject(): void
    {
        $this->assertSame('{}', json_encode(new SecretString(self::CANARY)));
    }

    public function testRevealAfterWipeThrows(): void
    {
        $secret = new SecretString(self::CANARY);
        $secret->wipe();

        $this->expectException(LogicException::class);

        $secret->reveal();
    }

    public function testWipeIsIdempotent(): void
    {
        $secret = new SecretString(self::CANARY);
        $secret->wipe();
        $secret->wipe();

        $this->expectException(LogicException::class);

        $secret->reveal();
    }

    public function testLogContextShowsTheClassNameOnly(): void
    {
        $log = new CapturedLog();

        $log->logger()->info('sending', ['password' => new SecretString(self::CANARY)]);

        $this->assertStringContainsString(json_encode(SecretString::class), $log->lines()[0]);
        $log->assertNotLogged(self::CANARY);
    }

    private function castToString(mixed $value): string
    {
        return (string) $value;
    }
}
