<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Pidgey\Http\JsonResponse;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;

/**
 * Test handler that answers {"id": <the "id" route param>}.
 */
final class ParamsHandler extends RequestHandler
{
    protected function process(ProcessingRequest $request): Response
    {
        return new JsonResponse(['id' => $request->param('id')]);
    }
}
