<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\BadRequestException;
use Pidgey\Http\HttpException;
use Pidgey\Http\PayloadTooLargeException;
use Pidgey\Http\Request;
use Pidgey\Http\UnsupportedMediaTypeException;

#[CoversClass(Request::class)]
#[UsesClass(BadRequestException::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(PayloadTooLargeException::class)]
#[UsesClass(UnsupportedMediaTypeException::class)]
final class RequestTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $savedServer = [];

    /** @var array<string, mixed> */
    private array $savedCookies = [];

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER;
        $this->savedCookies = $_COOKIE;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->savedServer;
        $_COOKIE = $this->savedCookies;
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

    public function testCookieIsReadFromTheGlobal(): void
    {
        $_COOKIE = ['access_token' => 'header.payload.signature'];

        $this->assertSame('header.payload.signature', (new Request())->cookie('access_token'));
    }

    public function testMissingCookieReturnsNull(): void
    {
        $_COOKIE = [];

        $this->assertNull((new Request())->cookie('access_token'));
    }

    public function testMissingCookieReturnsTheDefault(): void
    {
        $_COOKIE = ['other' => 'x'];

        $this->assertSame('fallback', (new Request())->cookie('access_token', 'fallback'));
    }

    public function testEmptyCookieReturnsEmptyStringNotTheDefault(): void
    {
        $this->assertSame('', (new Request(['cookies' => ['access_token' => '']]))->cookie('access_token', 'x'));
    }

    public function testArrayCookieReturnsTheDefault(): void
    {
        $_COOKIE = ['access_token' => ['x' => 'header.payload.signature']];

        $request = new Request();

        $this->assertNull($request->cookie('access_token'));
        $this->assertSame('fallback', $request->cookie('access_token', 'fallback'));
    }

    public function testCookiesOptionReplacesTheGlobal(): void
    {
        $_COOKIE = ['access_token' => 'from-global', 'other' => 'y'];

        $request = new Request(['cookies' => ['access_token' => 'from-option']]);

        $this->assertSame('from-option', $request->cookie('access_token'));
        $this->assertNull($request->cookie('other'));
    }

    public function testCookieLookupIsCaseSensitive(): void
    {
        $this->assertNull((new Request(['cookies' => ['access_token' => 'a']]))->cookie('Access_Token'));
    }

    public function testBodyOptionReplacesTheInput(): void
    {
        $this->assertSame('{"a":1}', (new Request(['body' => '{"a":1}']))->body());
    }

    public function testBodyIsReadFromTheInputWhenNotGiven(): void
    {
        $request = new Request(['headers' => []]);

        $this->assertSame('', $request->body());
        $this->assertSame('', $request->body());
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function anyContentTypeProvider(): array
    {
        return [
            'no content type' => [[]],
            'json' => [['Content-Type' => 'application/json']],
            'text' => [['Content-Type' => 'text/plain']],
            'zero length' => [['Content-Type' => 'text/plain', 'Content-Length' => '0']],
        ];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('anyContentTypeProvider')]
    public function testEmptyBodyIsAnEmptyArrayWhateverTheContentType(array $headers): void
    {
        $this->assertSame([], $this->jsonRequest('', $headers)->json());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function jsonContentTypeProvider(): array
    {
        return [
            'plain' => ['application/json'],
            'mixed case with charset' => ['Application/JSON; charset=UTF-8'],
            'lower-case charset' => ['application/json;charset=utf-8'],
            'quoted charset' => ['application/json; Charset="utf-8"'],
            'other parameter' => ['application/json; foo=bar'],
            'spaces' => [' application/json ; charset = utf-8 '],
        ];
    }

    #[DataProvider('jsonContentTypeProvider')]
    public function testParsesJsonContentTypes(string $contentType): void
    {
        $request = $this->jsonRequest('{"username":"ann"}', ['Content-Type' => $contentType]);

        $this->assertSame(['username' => 'ann'], $request->json());
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function unsupportedMediaTypeProvider(): array
    {
        return [
            'text/plain' => [['Content-Type' => 'text/plain']],
            'missing content type' => [[]],
            'empty content type' => [['Content-Type' => '']],
            'latin1 charset' => [['Content-Type' => 'application/json; charset=latin1']],
            'empty charset' => [['Content-Type' => 'application/json; charset=']],
            'json suffix' => [['Content-Type' => 'application/problem+json']],
        ];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('unsupportedMediaTypeProvider')]
    public function testNonJsonMediaTypeIs415(array $headers): void
    {
        $this->expectException(UnsupportedMediaTypeException::class);

        $this->jsonRequest('{}', $headers)->json();
    }

    public function testMediaTypeIsCheckedBeforeSize(): void
    {
        $this->expectException(UnsupportedMediaTypeException::class);

        $this->jsonRequest('{}', ['Content-Type' => 'text/plain', 'Content-Length' => '2000000'])->json();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function oversizedContentLengthProvider(): array
    {
        return [
            'just over' => [(string) (Request::MAX_BODY_BYTES + 1)],
            'huge' => ['99999999999999999999999999'],
            'leading zeros' => ['0001048577'],
        ];
    }

    #[DataProvider('oversizedContentLengthProvider')]
    public function testContentLengthOverTheLimitIs413WithoutReading(string $length): void
    {
        $this->expectException(PayloadTooLargeException::class);

        $this->jsonRequest('{}', ['Content-Type' => 'application/json', 'Content-Length' => $length])->json();
    }

    public function testContentLengthOverTheLimitIs413EvenWithAnEmptyBody(): void
    {
        $this->expectException(PayloadTooLargeException::class);

        $this->jsonRequest('', ['Content-Type' => 'application/json', 'Content-Length' => '2000000'])->json();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function acceptedContentLengthProvider(): array
    {
        return [
            'at the limit' => [(string) Request::MAX_BODY_BYTES],
            'leading zeros' => ['0001048576'],
            'not numeric' => ['abc'],
            'negative' => ['-5'],
        ];
    }

    #[DataProvider('acceptedContentLengthProvider')]
    public function testContentLengthWithinTheLimitOrNotNumericIsIgnored(string $length): void
    {
        $request = $this->jsonRequest('{"a":1}', ['Content-Type' => 'application/json', 'Content-Length' => $length]);

        $this->assertSame(['a' => 1], $request->json());
    }

    public function testBodyOverTheLimitWithoutContentLengthIs413(): void
    {
        $this->expectException(PayloadTooLargeException::class);

        $body = '{"a":"' . str_repeat('x', Request::MAX_BODY_BYTES) . '"}';
        $this->jsonRequest(substr($body, 0, Request::MAX_BODY_BYTES + 1))->json();
    }

    public function testBodyAtTheLimitIsAccepted(): void
    {
        $padding = Request::MAX_BODY_BYTES - strlen('{"a":""}');
        $body = '{"a":"' . str_repeat('x', $padding) . '"}';

        $this->assertSame(Request::MAX_BODY_BYTES, strlen($body));
        $this->assertSame(['a' => str_repeat('x', $padding)], $this->jsonRequest($body)->json());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badRequestBodyProvider(): array
    {
        return [
            'malformed' => ['{"a":'],
            'trailing comma' => ['{"a":1,}'],
            'invalid UTF-8' => ["{\"a\":\"\xC3\x28\"}"],
            'too deep' => [str_repeat('{"a":', 65) . '1' . str_repeat('}', 65)],
            'empty array' => ['[]'],
            'array' => ['[{"a":1}]'],
            'string' => ['"x"'],
            'number' => ['1'],
            'true' => ['true'],
            'null' => ['null'],
            'whitespace only' => ['   '],
        ];
    }

    #[DataProvider('badRequestBodyProvider')]
    public function testInvalidJsonOrNonObjectIs400(string $body): void
    {
        $this->expectException(BadRequestException::class);

        $this->jsonRequest($body)->json();
    }

    public function testParserErrorsCarryNoCustomMessage(): void
    {
        try {
            $this->jsonRequest('[]')->json();
            $this->fail('Expected a BadRequestException');
        } catch (BadRequestException $error) {
            $this->assertNull($error->errorCode());
            $this->assertNull($error->errorMessage());
        }
    }

    public function testNestingWithinTheDepthLimitIsAccepted(): void
    {
        $body = str_repeat('{"a":', 63) . '1' . str_repeat('}', 63);

        $this->assertIsArray($this->jsonRequest($body)->json());
    }

    public function testEmptyObjectIsAnEmptyArray(): void
    {
        $this->assertSame([], $this->jsonRequest('{}')->json());
    }

    public function testNestedObjectsBecomeArrays(): void
    {
        $body = '{"a":{"b":{"c":true}},"list":[{"d":null},2],"empty":{}}';

        $this->assertSame(
            ['a' => ['b' => ['c' => true]], 'list' => [['d' => null], 2], 'empty' => []],
            $this->jsonRequest($body)->json(),
        );
    }

    public function testUnknownFieldsAreKept(): void
    {
        $body = '{"password":"x","password_confirmation":"x","extra":1}';

        $this->assertSame(
            ['password' => 'x', 'password_confirmation' => 'x', 'extra' => 1],
            $this->jsonRequest($body)->json(),
        );
    }

    public function testJsonIsCached(): void
    {
        $request = $this->jsonRequest('{"a":[1,2]}');

        $first = $request->json();

        $this->assertSame(['a' => [1, 2]], $first);
        $this->assertSame($first, $request->json());
    }

    /**
     * @param array<string, string> $headers
     */
    private function jsonRequest(string $body, array $headers = ['Content-Type' => 'application/json']): Request
    {
        return new Request(['method' => 'POST', 'uri' => '/x.json', 'headers' => $headers, 'body' => $body]);
    }
}
