<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;
use Pidgey\Http\Response;

#[CoversClass(ProcessingRequest::class)]
#[UsesClass(Request::class)]
#[UsesClass(Response::class)]
final class ProcessingRequestTest extends TestCase
{
    public function testExposesTheWrappedRequest(): void
    {
        $request = new Request(['method' => 'GET', 'uri' => '/']);

        $this->assertSame($request, (new ProcessingRequest($request))->request());
    }

    public function testDelegatesToTheRequest(): void
    {
        $processing = new ProcessingRequest(new Request(['method' => 'post', 'uri' => '/a%20b?c=d']));

        $this->assertSame('POST', $processing->method());
        $this->assertSame('/a b', $processing->path());
        $this->assertSame('c=d', $processing->query());
    }

    public function testHasNoResponseInitially(): void
    {
        $processing = new ProcessingRequest(new Request(['method' => 'GET', 'uri' => '/']));

        $this->assertFalse($processing->hasResponse());
        $this->assertNull($processing->response());
    }

    public function testRespondSetsTheResponse(): void
    {
        $processing = new ProcessingRequest(new Request(['method' => 'GET', 'uri' => '/']));
        $response = new Response('early', 304);

        $processing->respond($response);

        $this->assertTrue($processing->hasResponse());
        $this->assertSame($response, $processing->response());
    }

    public function testSecondRespondThrows(): void
    {
        $processing = new ProcessingRequest(new Request(['method' => 'GET', 'uri' => '/']));
        $first = new Response('first');
        $processing->respond($first);

        try {
            $processing->respond(new Response('second'));
            $this->fail('Expected a LogicException.');
        } catch (LogicException) {
            $this->assertSame($first, $processing->response());
        }
    }
}
