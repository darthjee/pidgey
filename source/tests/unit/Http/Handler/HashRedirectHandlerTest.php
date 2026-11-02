<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\Handler\HashRedirectHandler;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;

#[CoversClass(HashRedirectHandler::class)]
#[UsesClass(ProcessingRequest::class)]
#[UsesClass(Request::class)]
#[UsesClass(RequestHandler::class)]
#[UsesClass(Response::class)]
final class HashRedirectHandlerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function redirectProvider(): array
    {
        return [
            'query string kept' => ['/foo?x=1', '/#/foo?x=1'],
            'trailing slash kept' => ['/foo/', '/#/foo/'],
            'encoded CRLF stays encoded' => ['/a%0D%0Ab', '/#/a%0D%0Ab'],
            'protocol-relative stays on origin' => ['//evil.com', '/#//evil.com'],
        ];
    }

    #[DataProvider('redirectProvider')]
    public function testRedirectsToTheHashRoute(string $uri, string $location): void
    {
        $request = new ProcessingRequest(new Request(['method' => 'GET', 'uri' => $uri]));

        $response = (new HashRedirectHandler())->handle($request, []);

        $this->assertSame(302, $response->status());
        $this->assertSame(['Location: ' . $location], $response->headers());
        $this->assertSame('', $response->body());
    }
}
