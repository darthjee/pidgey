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
final class RequestBodySizeTest extends RequestTestCase
{
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
}
