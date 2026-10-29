<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use Pidgey\Tests\Support\RecordingMiddleware;
use RuntimeException;

#[CoversClass(RequestHandler::class)]
#[UsesClass(ProcessingRequest::class)]
#[UsesClass(Request::class)]
#[UsesClass(Response::class)]
final class RequestHandlerTest extends TestCase
{
    /** @var list<string> */
    private array $log = [];

    private Response $handlerResponse;

    private ?ProcessingRequest $processed = null;

    protected function setUp(): void
    {
        $this->handlerResponse = new Response('handler');
    }

    public function testWithoutMiddlewaresReturnsTheProcessResponse(): void
    {
        $response = $this->handler()->handle($this->request(), []);

        $this->assertSame($this->handlerResponse, $response);
        $this->assertSame(['handler:process'], $this->log);
    }

    public function testProcessReceivesTheRequest(): void
    {
        $request = $this->request();

        $this->handler()->handle($request, []);

        $this->assertSame($request, $this->processed);
    }

    public function testRunsHooksInRegistrationOrder(): void
    {
        $response = $this->handler()->handle($this->request(), [
            new RecordingMiddleware('first', $this->log),
            new RecordingMiddleware('second', $this->log),
        ]);

        $this->assertSame($this->handlerResponse, $response);
        $this->assertSame(
            ['first:request', 'second:request', 'handler:process', 'first:response', 'second:response'],
            $this->log,
        );
    }

    public function testEarlyResponseSkipsLaterRequestHooksAndProcess(): void
    {
        $early = new Response('early', 304);

        $response = $this->handler()->handle($this->request(), [
            new RecordingMiddleware('first', $this->log, earlyResponse: $early),
            new RecordingMiddleware('second', $this->log),
        ]);

        $this->assertSame($early, $response);
        $this->assertSame(['first:request', 'first:response', 'second:response'], $this->log);
    }

    public function testResponseHooksChainTheirResults(): void
    {
        $replacement = new Response('replaced');
        $final = new Response('final');

        $response = $this->handler()->handle($this->request(), [
            new RecordingMiddleware('first', $this->log, replacement: $replacement),
            new RecordingMiddleware('second', $this->log),
            new RecordingMiddleware('third', $this->log, replacement: $final),
        ]);

        $this->assertSame($final, $response);
    }

    public function testResponseHookReceivesThePreviousResult(): void
    {
        $replacement = new Response('replaced');

        $response = $this->handler()->handle($this->request(), [
            new RecordingMiddleware('first', $this->log, replacement: $replacement),
            new RecordingMiddleware('second', $this->log),
        ]);

        $this->assertSame($replacement, $response);
    }

    public function testRequestHookExceptionPropagates(): void
    {
        $error = new RuntimeException('request hook');

        try {
            $this->handler()->handle($this->request(), [
                new RecordingMiddleware('first', $this->log, requestError: $error),
                new RecordingMiddleware('second', $this->log),
            ]);
            $this->fail('Expected the exception to propagate.');
        } catch (RuntimeException $caught) {
            $this->assertSame($error, $caught);
        }

        $this->assertSame(['first:request'], $this->log);
    }

    public function testProcessExceptionPropagates(): void
    {
        $error = new RuntimeException('process');

        try {
            $this->handler($error)->handle($this->request(), [new RecordingMiddleware('first', $this->log)]);
            $this->fail('Expected the exception to propagate.');
        } catch (RuntimeException $caught) {
            $this->assertSame($error, $caught);
        }

        $this->assertSame(['first:request', 'handler:process'], $this->log);
    }

    public function testResponseHookExceptionPropagates(): void
    {
        $error = new RuntimeException('response hook');

        try {
            $this->handler()->handle($this->request(), [
                new RecordingMiddleware('first', $this->log, responseError: $error),
                new RecordingMiddleware('second', $this->log),
            ]);
            $this->fail('Expected the exception to propagate.');
        } catch (RuntimeException $caught) {
            $this->assertSame($error, $caught);
        }

        $this->assertSame(['first:request', 'second:request', 'handler:process', 'first:response'], $this->log);
    }

    private function request(): ProcessingRequest
    {
        return new ProcessingRequest(new Request(['method' => 'GET', 'uri' => '/']));
    }

    private function handler(?RuntimeException $error = null): RequestHandler
    {
        $log = &$this->log;
        $processed = &$this->processed;
        $response = $this->handlerResponse;

        return new class ($log, $processed, $response, $error) extends RequestHandler {
            /**
             * @param list<string> $log
             */
            public function __construct(
                private array &$log,
                private ?ProcessingRequest &$processed,
                private readonly Response $response,
                private readonly ?RuntimeException $error,
            ) {
            }

            protected function process(ProcessingRequest $request): Response
            {
                $this->log[] = 'handler:process';
                $this->processed = $request;

                if ($this->error !== null) {
                    throw $this->error;
                }

                return $this->response;
            }
        };
    }
}
