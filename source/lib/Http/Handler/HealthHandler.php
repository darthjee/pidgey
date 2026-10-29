<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use Pidgey\Http\JsonResponse;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;

/**
 * Liveness check for GET /health.json: answers {"status":"ok"} and touches nothing
 * (no database, SMTP or file).
 */
final class HealthHandler extends RequestHandler
{
    /**
     * Answers 200 with {"status":"ok"}.
     *
     * @param ProcessingRequest $request The request being dispatched (unused).
     *
     * @return Response
     */
    protected function process(ProcessingRequest $request): Response
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
