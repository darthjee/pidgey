<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use LogicException;
use Pidgey\Container;
use Pidgey\Http\Middleware;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Response;
use Throwable;

/**
 * Test double that logs "<name>:request" / "<name>:response" into a shared array, and can
 * answer early, replace the response or throw from either hook.
 */
final class RecordingMiddleware implements Middleware
{
    /**
     * @param list<string> $log Shared log, written by reference.
     */
    public function __construct(
        private readonly string $name,
        private array &$log,
        private readonly ?Response $earlyResponse = null,
        private readonly ?Response $replacement = null,
        private readonly ?Throwable $requestError = null,
        private readonly ?Throwable $responseError = null,
    ) {
    }

    /**
     * Needs a shared log by reference, so it cannot come from the container: tests build it directly.
     */
    public static function build(Container $container): static
    {
        throw new LogicException('RecordingMiddleware is built directly by tests');
    }

    public function processRequest(ProcessingRequest $request): void
    {
        $this->log[] = "{$this->name}:request";

        if ($this->requestError !== null) {
            throw $this->requestError;
        }

        if ($this->earlyResponse !== null) {
            $request->respond($this->earlyResponse);
        }
    }

    public function processResponse(ProcessingRequest $request, Response $response): Response
    {
        $this->log[] = "{$this->name}:response";

        if ($this->responseError !== null) {
            throw $this->responseError;
        }

        return $this->replacement ?? $response;
    }
}
