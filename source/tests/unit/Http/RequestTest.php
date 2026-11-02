<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\Request;

#[CoversClass(Request::class)]
final class RequestTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $savedServer = [];

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->savedServer;
    }

    public function testMethodIsUpperCased(): void
    {
        $this->assertSame('POST', (new Request(['method' => 'post']))->method());
    }

    public function testMethodDefaultsToGet(): void
    {
        unset($_SERVER['REQUEST_METHOD']);

        $this->assertSame('GET', (new Request())->method());
    }

    public function testMethodFallsBackToServerGlobal(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'delete';

        $this->assertSame('DELETE', (new Request())->method());
    }

    public function testUriFallsBackToServerGlobal(): void
    {
        $_SERVER['REQUEST_URI'] = '/from/server?x=1';

        $request = new Request();

        $this->assertSame('/from/server', $request->path());
        $this->assertSame('x=1', $request->query());
    }

    public function testOptionsWinOverServerGlobals(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI'] = '/from/server';

        $request = new Request(['method' => 'put', 'uri' => '/from/option']);

        $this->assertSame('PUT', $request->method());
        $this->assertSame('/from/option', $request->path());
    }

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
