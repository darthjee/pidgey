<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\OriginParser;

#[CoversClass(OriginParser::class)]
final class OriginParserTest extends TestCase
{
    /**
     * @return array<string, array{string, array{scheme: string, host: string, port: int}}>
     */
    public static function origins(): array
    {
        return [
            'https default port' => ['https://a.test', ['scheme' => 'https', 'host' => 'a.test', 'port' => 443]],
            'http default port' => ['http://a.test', ['scheme' => 'http', 'host' => 'a.test', 'port' => 80]],
            'explicit port' => ['http://a.test:8080', ['scheme' => 'http', 'host' => 'a.test', 'port' => 8080]],
            'max port' => ['http://a.test:65535', ['scheme' => 'http', 'host' => 'a.test', 'port' => 65535]],
            'ipv6' => ['http://[::1]:8080', ['scheme' => 'http', 'host' => '[::1]', 'port' => 8080]],
            'host case kept' => ['https://A.Test', ['scheme' => 'https', 'host' => 'A.Test', 'port' => 443]],
        ];
    }

    /**
     * @param array{scheme: string, host: string, port: int} $expected
     */
    #[DataProvider('origins')]
    public function testParsesOrigins(string $origin, array $expected): void
    {
        $this->assertSame($expected, OriginParser::parseOrigin($origin));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unparseableOrigins(): array
    {
        return [
            'null' => ['null'],
            'empty' => [''],
            'trailing slash' => ['https://a.test/'],
            'path' => ['https://a.test/x'],
            'query' => ['https://a.test?x'],
            'fragment' => ['https://a.test#x'],
            'user info' => ['https://u@a.test'],
            'ftp' => ['ftp://a.test'],
            'upper-case scheme' => ['HTTPS://a.test'],
            'no host' => ['https://'],
            'empty port' => ['https://a.test:'],
            'port 0' => ['https://a.test:0'],
            'port too big' => ['https://a.test:65536'],
            'six-digit port' => ['https://a.test:100000'],
            'trailing newline' => ["https://a.test\n"],
            'space' => ['https://a .test'],
            'unbracketed ipv6' => ['http://::1'],
        ];
    }

    #[DataProvider('unparseableOrigins')]
    public function testRejectsUnparseableOrigins(string $origin): void
    {
        $this->assertNull(OriginParser::parseOrigin($origin));
    }

    public function testParsesHostsWithTheDefaultPort(): void
    {
        $this->assertSame(['host' => 'a.test', 'port' => 443], OriginParser::parseHost('a.test', 443));
        $this->assertSame(['host' => 'a.test', 'port' => 8080], OriginParser::parseHost('a.test:8080', 443));
        $this->assertSame(['host' => '[::1]', 'port' => 80], OriginParser::parseHost('[::1]', 80));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unparseableHosts(): array
    {
        return [
            'empty' => [''],
            'path' => ['a.test/x'],
            'user info' => ['u@a.test'],
            'scheme' => ['https://a.test'],
            'empty port' => ['a.test:'],
            'port 0' => ['a.test:0'],
            'trailing newline' => ["a.test\n"],
        ];
    }

    #[DataProvider('unparseableHosts')]
    public function testRejectsUnparseableHosts(string $host): void
    {
        $this->assertNull(OriginParser::parseHost($host, 80));
    }
}
