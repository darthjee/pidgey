<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\SecretKeys;
use Pidgey\Infra\Env;

#[CoversClass(SecretKeys::class)]
#[UsesClass(Env::class)]
final class SecretKeysTest extends TestCase
{
    private const CURRENT = 'test-secret-value-not-a-real-secret-current';
    private const FIRST = 'test-secret-value-not-a-real-secret-first';
    private const SECOND = 'test-secret-value-not-a-real-secret-second';

    /** @var array<string, string|false> */
    private array $saved = [];

    protected function setUp(): void
    {
        foreach (['SECRET_KEY', 'PREVIOUS_SECRET_KEYS'] as $name) {
            $this->saved[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }
    }

    public function testVerificationKeysAreTheCurrentKeyThenThePreviousOnes(): void
    {
        $keys = new SecretKeys(self::CURRENT, [self::FIRST, self::SECOND]);

        $this->assertSame([self::CURRENT, self::FIRST, self::SECOND], $keys->verificationKeys());
    }

    public function testReadsTheCurrentKeyAlone(): void
    {
        putenv('SECRET_KEY=' . self::CURRENT);

        $keys = SecretKeys::fromEnv();

        $this->assertSame(self::CURRENT, $keys->current);
        $this->assertSame([], $keys->previous);
    }

    public function testReadsTrimmedPreviousKeysInOrderIgnoringEmptyEntries(): void
    {
        putenv('SECRET_KEY=' . self::CURRENT);
        putenv('PREVIOUS_SECRET_KEYS= ' . self::FIRST . ' ,, ' . self::SECOND . ',');

        $keys = SecretKeys::fromEnv();

        $this->assertSame([self::FIRST, self::SECOND], $keys->previous);
        $this->assertSame([self::CURRENT, self::FIRST, self::SECOND], $keys->verificationKeys());
    }

    public function testDebugDumpsHideTheKeys(): void
    {
        $dump = print_r(new SecretKeys(self::CURRENT, [self::FIRST]), true);

        $this->assertStringNotContainsString(self::CURRENT, $dump);
        $this->assertStringNotContainsString(self::FIRST, $dump);
    }
}
