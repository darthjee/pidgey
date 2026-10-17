<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\Env;
use RuntimeException;

#[CoversClass(Env::class)]
final class EnvTest extends TestCase
{
    private const NAME = 'PIDGEY_ENV_TEST_VARIABLE';

    protected function setUp(): void
    {
        putenv(self::NAME);
    }

    protected function tearDown(): void
    {
        putenv(self::NAME);
    }

    public function testGetReturnsValueWhenSet(): void
    {
        putenv(self::NAME . '=value');

        $this->assertSame('value', Env::get(self::NAME));
    }

    public function testGetReturnsValueOverDefaultWhenSet(): void
    {
        putenv(self::NAME . '=value');

        $this->assertSame('value', Env::get(self::NAME, 'default'));
    }

    public function testGetReturnsNullWhenUnsetWithoutDefault(): void
    {
        $this->assertNull(Env::get(self::NAME));
    }

    public function testGetReturnsDefaultWhenUnset(): void
    {
        $this->assertSame('default', Env::get(self::NAME, 'default'));
    }

    public function testGetTreatsEmptyAsUnset(): void
    {
        putenv(self::NAME . '=');

        $this->assertNull(Env::get(self::NAME));
        $this->assertSame('default', Env::get(self::NAME, 'default'));
    }

    public function testRequiredReturnsValueWhenSet(): void
    {
        putenv(self::NAME . '=value');

        $this->assertSame('value', Env::required(self::NAME));
    }

    public function testRequiredThrowsWhenUnset(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required environment variable: ' . self::NAME);

        Env::required(self::NAME);
    }

    public function testRequiredThrowsWhenEmpty(): void
    {
        putenv(self::NAME . '=');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required environment variable: ' . self::NAME);

        Env::required(self::NAME);
    }
}
