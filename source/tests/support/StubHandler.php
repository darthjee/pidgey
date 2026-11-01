<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;

/**
 * Test handler that answers 200 with "stub".
 */
final class StubHandler extends RequestHandler
{
    protected function process(ProcessingRequest $request): Response
    {
        return new Response('stub');
    }
}
