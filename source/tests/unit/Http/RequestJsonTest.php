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
final class RequestJsonTest extends RequestTestCase
{
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
}
