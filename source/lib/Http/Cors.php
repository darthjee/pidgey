<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * The CORS front-controller step (request-handler/front-controller.md#cors-step-pidgeyhttpcors).
 *
 * Answers preflights before routing and adds the CORS headers to every final response. Only
 * exactly trusted origins are echoed; `*` is never sent and the step never changes a status or
 * a body.
 */
final class Cors
{
    public const ALLOW_METHODS = 'GET, POST, PATCH, DELETE';

    public const ALLOW_HEADERS = 'Content-Type';

    private readonly TrustedOrigins $trusted;

    /**
     * Builds the step.
     *
     * @param TrustedOrigins|null $trusted Trusted origins; defaults to TrustedOrigins::fromEnv().
     */
    public function __construct(?TrustedOrigins $trusted = null)
    {
        $this->trusted = $trusted ?? TrustedOrigins::fromEnv();
    }

    /**
     * Answers a preflight: an OPTIONS request with both Origin and Access-Control-Request-Method.
     *
     * A trusted origin gets the allowed methods and headers; an untrusted one gets a bare 204.
     * The allow-origin, credentials and Vary headers are added later by addHeaders().
     *
     * @param Request $request The incoming request.
     *
     * @return Response|null The 204 response, or null when the request is not a preflight.
     */
    public function preflight(Request $request): ?Response
    {
        $origin = $request->header('Origin');

        if (
            $request->method() !== 'OPTIONS'
            || $origin === null
            || $request->header('Access-Control-Request-Method') === null
        ) {
            return null;
        }

        $response = new Response('', 204);

        if ($this->trusted->contains($origin)) {
            $response->addHeader('Access-Control-Allow-Methods: ' . self::ALLOW_METHODS);
            $response->addHeader('Access-Control-Allow-Headers: ' . self::ALLOW_HEADERS);
        }

        return $response;
    }

    /**
     * Adds Vary: Origin and, for a trusted Origin, the allow-origin and credentials headers.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The final response.
     *
     * @return Response The same response instance.
     */
    public function addHeaders(Request $request, Response $response): Response
    {
        $response->addHeader('Vary: Origin');

        $origin = $request->header('Origin');

        if ($origin !== null && $this->trusted->contains($origin)) {
            $response->addHeader('Access-Control-Allow-Origin: ' . $origin);
            $response->addHeader('Access-Control-Allow-Credentials: true');
        }

        return $response;
    }
}
