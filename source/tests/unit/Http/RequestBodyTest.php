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
final class RequestBodyTest extends RequestTestCase
{
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
}
