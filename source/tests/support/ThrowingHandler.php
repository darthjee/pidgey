<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Pidgey\Http\HttpException;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use RuntimeException;

/**
 * Test handler that throws: an HttpException 409 on paths ending in "/http.json",
 * otherwise a RuntimeException with a message that must never leak.
 */
final class ThrowingHandler extends RequestHandler
{
    public const SECRET = 'secret database password';

    protected function process(ProcessingRequest $request): Response
    {
        if (str_ends_with($request->path(), '/http.json')) {
            throw new HttpException(409, 'USERNAME_TAKEN', 'Username already in use');
        }

        throw new RuntimeException(self::SECRET);
    }
}
