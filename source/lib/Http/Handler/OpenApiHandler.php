<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use Pidgey\Container;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use RuntimeException;

/**
 * Serves the committed `/v1` OpenAPI document verbatim on GET and HEAD /v1/openapi.json.
 *
 * Public: the route has no guard and credentials are ignored. The file is read from a path fixed
 * by the source tree (source/openapi/v1.json), never from the request; no database is touched.
 * A HEAD request gets the same status and headers with an empty body. No cache headers.
 */
final class OpenApiHandler extends RequestHandler
{
    private readonly string $file;

    /**
     * Builds the handler.
     *
     * @param string|null $file Path of the OpenAPI document; defaults to source/openapi/v1.json.
     */
    public function __construct(?string $file = null)
    {
        $this->file = $file ?? dirname(__DIR__, 3) . '/openapi/v1.json';
    }

    /**
     * Builds the handler with the default document path.
     *
     * @param Container $container The request's container (unused).
     *
     * @return static
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public static function build(Container $container): static
    {
        return new self();
    }

    /**
     * Answers 200 with the document, or with an empty body for HEAD.
     *
     * @param ProcessingRequest $request The request being dispatched.
     *
     * @return Response
     *
     * @throws RuntimeException When the document cannot be read (answered as the 500 envelope).
     */
    protected function process(ProcessingRequest $request): Response
    {
        $headers = ['Content-Type: application/json'];

        if ($request->method() === 'HEAD') {
            return new Response('', 200, $headers);
        }

        $body = @file_get_contents($this->file);

        if ($body === false) {
            throw new RuntimeException('The OpenAPI document cannot be read.');
        }

        return new Response($body, 200, $headers);
    }
}
