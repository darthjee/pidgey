<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;

/**
 * GET fallback: turns a deep link into a hash route of the single-page application.
 *
 * Answers 302 with "Location: /#<raw REQUEST_URI>" and an empty body. The URI is used as
 * received, never decoded or normalised, so the query string and the trailing slash are kept.
 * This is safe: the Location always starts with "/#", so it stays on Pidgey's origin
 * ("//evil.com" becomes "/#//evil.com", no open redirect), and the raw URI keeps CR/LF
 * percent-encoded, while Response::addHeader() rejects CR/LF anyway.
 */
final class HashRedirectHandler extends RequestHandler
{
    /**
     * Answers 302 to the hash route of the raw request URI.
     *
     * @param ProcessingRequest $request The request being dispatched.
     *
     * @return Response
     */
    protected function process(ProcessingRequest $request): Response
    {
        return new Response('', 302, ['Location: /#' . $request->uri()]);
    }
}
