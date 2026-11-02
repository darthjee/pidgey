<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\TrustedOrigins;
use Pidgey\Infra\Env;

#[CoversClass(TrustedOrigins::class)]
#[UsesClass(Env::class)]
final class TrustedOriginsTest extends TestCase
{
    private const VARIABLES = ['ALLOWED_ORIGINS', 'FRONTEND_BASE_URL'];

    /** @var array<string, string|false> */
    private array $saved = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
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

    public function testReadsAllowedOrigins(): void
    {
        putenv('ALLOWED_ORIGINS=https://a.test,https://b.test:8443');
        putenv('FRONTEND_BASE_URL=https://frontend.test');

        $this->assertSame(['https://a.test', 'https://b.test:8443'], TrustedOrigins::fromEnv()->all());
    }

    public function testTrimsEntriesAndDropsEmptyOnes(): void
    {
        putenv('ALLOWED_ORIGINS= https://a.test , ,,https://b.test ,');

        $this->assertSame(['https://a.test', 'https://b.test'], TrustedOrigins::fromEnv()->all());
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function frontendBaseUrlProvider(): array
    {
        return [
            'bare origin' => ['https://app.test', ['https://app.test']],
            'with port' => ['http://localhost:5173', ['http://localhost:5173']],
            'with path' => ['https://app.test/admin/?x=1#y', ['https://app.test']],
            'with port and path' => ['https://app.test:8443/admin', ['https://app.test:8443']],
            'ipv6' => ['http://[::1]:8080/', ['http://[::1]:8080']],
            'unparseable' => ['http:///', []],
            'no scheme' => ['app.test', []],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('frontendBaseUrlProvider')]
    public function testFallsBackToFrontendBaseUrl(string $url, array $expected): void
    {
        putenv('FRONTEND_BASE_URL=' . $url);

        $this->assertSame($expected, TrustedOrigins::fromEnv()->all());
    }

    public function testFallsBackWhenAllowedOriginsHasNoEntries(): void
    {
        putenv('ALLOWED_ORIGINS= , ');
        putenv('FRONTEND_BASE_URL=https://app.test/');

        $this->assertSame(['https://app.test'], TrustedOrigins::fromEnv()->all());
    }

    public function testIsEmptyWhenBothAreEmpty(): void
    {
        putenv('ALLOWED_ORIGINS=');
        putenv('FRONTEND_BASE_URL=');

        $this->assertSame([], TrustedOrigins::fromEnv()->all());
    }

    public function testContainsAnExactMatch(): void
    {
        $origins = new TrustedOrigins(['https://a.test', 'http://b.test:8080']);

        $this->assertTrue($origins->contains('http://b.test:8080'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonMatchingProvider(): array
    {
        return [
            'other scheme' => ['http://a.test'],
            'explicit default port' => ['https://a.test:443'],
            'other port' => ['https://a.test:8443'],
            'upper-case host' => ['https://A.test'],
            'trailing slash' => ['https://a.test/'],
            'subdomain' => ['https://evil.a.test'],
            'prefix' => ['https://a.test.evil'],
            'empty' => [''],
            'null' => ['null'],
        ];
    }

    #[DataProvider('nonMatchingProvider')]
    public function testOnlyMatchesExactly(string $origin): void
    {
        $this->assertFalse((new TrustedOrigins(['https://a.test']))->contains($origin));
    }

    public function testNullIsNeverTrustedEvenWhenListed(): void
    {
        $this->assertFalse((new TrustedOrigins(['null']))->contains('null'));
    }
}
