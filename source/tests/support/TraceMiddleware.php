<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Pidgey\Http\BaseMiddleware;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Response;

/**
 * Test middleware built without arguments: its response hook appends "X-Trace: <short class name>".
 */
abstract class TraceMiddleware extends BaseMiddleware
{
    public function processResponse(ProcessingRequest $request, Response $response): Response
    {
        $response->addHeader('X-Trace: ' . substr(strrchr(static::class, '\\'), 1));

        return $response;
    }
}
