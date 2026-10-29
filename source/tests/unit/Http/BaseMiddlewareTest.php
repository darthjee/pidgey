<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\BaseMiddleware;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;
use Pidgey\Http\Response;

#[CoversClass(BaseMiddleware::class)]
#[UsesClass(ProcessingRequest::class)]
#[UsesClass(Request::class)]
#[UsesClass(Response::class)]
final class BaseMiddlewareTest extends TestCase
{
    public function testProcessRequestDoesNothing(): void
    {
        $request = new ProcessingRequest(new Request(['method' => 'GET', 'uri' => '/']));

        $this->middleware()->processRequest($request);

        $this->assertFalse($request->hasResponse());
    }

    public function testProcessResponseReturnsTheSameResponse(): void
    {
        $request = new ProcessingRequest(new Request(['method' => 'GET', 'uri' => '/']));
        $response = new Response('body');

        $this->assertSame($response, $this->middleware()->processResponse($request, $response));
    }

    private function middleware(): BaseMiddleware
    {
        return new class extends BaseMiddleware {
        };
    }
}
