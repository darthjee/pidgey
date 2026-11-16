<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Http\BadRequestException;
use Pidgey\Http\HttpException;
use Pidgey\Http\PayloadTooLargeException;
use Pidgey\Http\Request;
use Pidgey\Http\UnsupportedMediaTypeException;
use Pidgey\Tests\Support\RequestTestCase;

#[CoversClass(Request::class)]
#[UsesClass(BadRequestException::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(PayloadTooLargeException::class)]
#[UsesClass(UnsupportedMediaTypeException::class)]
final class RequestPathTest extends RequestTestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function pathProvider(): array
    {
        return [
            'plain' => ['/health.json', '/health.json'],
            'encoded space' => ['/a%20b', '/a b'],
            'encoded plus' => ['/a%2Bb', '/a+b'],
            'literal plus' => ['/a+b', '/a+b'],
            'query dropped' => ['/users.json?page=2', '/users.json'],
            'root' => ['/', '/'],
            'query only' => ['?a=b', '/'],
            'empty' => ['', '/'],
            'unparsable' => ['http:///', '/'],
        ];
    }

    #[DataProvider('pathProvider')]
    public function testPath(string $uri, string $expected): void
    {
        $this->assertSame($expected, (new Request(['uri' => $uri]))->path());
    }

    public function testPathDefaultsToRootWithoutUri(): void
    {
        unset($_SERVER['REQUEST_URI']);

        $this->assertSame('/', (new Request())->path());
    }

    public function testQueryIsRaw(): void
    {
        $this->assertSame('a=1&b=%20c', (new Request(['uri' => '/x?a=1&b=%20c']))->query());
    }

    public function testQueryIsEmptyWhenAbsent(): void
    {
        $this->assertSame('', (new Request(['uri' => '/x']))->query());
    }

    public function testQueryIsEmptyWithoutUri(): void
    {
        unset($_SERVER['REQUEST_URI']);

        $this->assertSame('', (new Request())->query());
    }

    public function testQueryIsEmptyForUnparsableUri(): void
    {
        $this->assertSame('', (new Request(['uri' => 'http:///']))->query());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rawUriProvider(): array
    {
        return [
            'encoded CRLF and query' => ['/foo%0D%0A?x=1'],
            'protocol-relative' => ['//evil.com'],
            'trailing slash' => ['/foo/'],
        ];
    }

    #[DataProvider('rawUriProvider')]
    public function testUriIsReturnedUnchanged(string $uri): void
    {
        $this->assertSame($uri, (new Request(['uri' => $uri]))->uri());
    }

    public function testUriDefaultsToRootWithoutUri(): void
    {
        unset($_SERVER['REQUEST_URI']);

        $this->assertSame('/', (new Request())->uri());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nulPathProvider(): array
    {
        return [
            'encoded NUL' => ['/a%00b.json'],
            'encoded NUL with query' => ['/%00?x=1'],
        ];
    }

    #[DataProvider('nulPathProvider')]
    public function testPathRejectsADecodedNulByte(string $uri): void
    {
        $this->expectException(BadRequestException::class);

        (new Request(['uri' => $uri]))->path();
    }

    public function testNulByteInTheQueryIsNotRejectedByPath(): void
    {
        $this->assertSame('/a', (new Request(['uri' => '/a?x=%00']))->path());
    }
}
