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
final class RequestHeaderTest extends RequestTestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function headerNameProvider(): array
    {
        return [
            'canonical' => ['X-Request-Id'],
            'lower-case' => ['x-request-id'],
            'upper-case' => ['X-REQUEST-ID'],
            'server key without prefix' => ['X_REQUEST_ID'],
        ];
    }

    #[DataProvider('headerNameProvider')]
    public function testHeaderLookupIgnoresCase(string $name): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = 'abc';

        $this->assertSame('abc', (new Request())->header($name));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unprefixedHeaderProvider(): array
    {
        return [
            'Content-Type' => ['Content-Type', 'CONTENT_TYPE'],
            'content-type' => ['content-type', 'CONTENT_TYPE'],
            'CONTENT_TYPE' => ['CONTENT_TYPE', 'CONTENT_TYPE'],
            'Content-Length' => ['Content-Length', 'CONTENT_LENGTH'],
            'content-length' => ['content-length', 'CONTENT_LENGTH'],
        ];
    }

    #[DataProvider('unprefixedHeaderProvider')]
    public function testContentHeadersMapWithoutPrefix(string $name, string $key): void
    {
        unset($_SERVER['HTTP_CONTENT_TYPE'], $_SERVER['HTTP_CONTENT_LENGTH']);
        $_SERVER[$key] = 'value';

        $this->assertSame('value', (new Request())->header($name));
    }

    public function testContentTypeIgnoresAPrefixedKey(): void
    {
        unset($_SERVER['CONTENT_TYPE']);
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';

        $this->assertNull((new Request())->header('Content-Type'));
    }

    public function testMissingHeaderReturnsNull(): void
    {
        unset($_SERVER['HTTP_X_MISSING']);

        $this->assertNull((new Request())->header('X-Missing'));
    }

    public function testMissingHeaderReturnsTheDefault(): void
    {
        unset($_SERVER['HTTP_X_MISSING']);

        $this->assertSame('fallback', (new Request())->header('X-Missing', 'fallback'));
    }

    public function testEmptyHeaderReturnsEmptyStringNotTheDefault(): void
    {
        $_SERVER['HTTP_X_EMPTY'] = '';

        $this->assertSame('', (new Request())->header('X-Empty', 'fallback'));
    }

    public function testHeaderValueIsRaw(): void
    {
        $_SERVER['HTTP_X_DUP'] = '  a, b  ';

        $this->assertSame('  a, b  ', (new Request())->header('X-Dup'));
    }

    public function testNonHeaderServerKeysAreNotHeaders(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->assertNull((new Request())->header('Request-Method'));
    }

    public function testHeadersOptionAcceptsHeaderNames(): void
    {
        $request = new Request(['headers' => ['Content-Type' => 'application/json', 'sec-fetch-site' => 'none']]);

        $this->assertSame('application/json', $request->header('content-type'));
        $this->assertSame('none', $request->header('Sec-Fetch-Site'));
    }

    public function testHeadersOptionAcceptsServerKeys(): void
    {
        $request = new Request(['headers' => ['HTTP_ORIGIN' => 'https://a.test', 'CONTENT_LENGTH' => '12']]);

        $this->assertSame('https://a.test', $request->header('Origin'));
        $this->assertSame('12', $request->header('Content-Length'));
    }

    public function testHeadersOptionReplacesServerHeaders(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.test';

        $request = new Request(['headers' => ['Host' => 'pidgey.test']]);

        $this->assertNull($request->header('Origin'));
        $this->assertSame('pidgey.test', $request->header('Host'));
    }

    public function testHeadersOptionKeepsEmptyValues(): void
    {
        $this->assertSame('', (new Request(['headers' => ['Origin' => '']]))->header('Origin', 'x'));
    }
}
