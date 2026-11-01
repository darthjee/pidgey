<?php

declare(strict_types=1);

namespace Pidgey\Http;

use LogicException;

/**
 * Per-dispatch wrapper around the Request, carrying the route params and the early response.
 */
final class ProcessingRequest
{
    private ?Response $response = null;

    /**
     * Wraps the given request.
     *
     * @param Request               $request The request being dispatched.
     * @param array<string, string> $params  Params captured by the matched route, decoded.
     */
    public function __construct(private readonly Request $request, private readonly array $params = [])
    {
    }

    /**
     * Returns the wrapped request.
     *
     * @return Request
     */
    public function request(): Request
    {
        return $this->request;
    }

    /**
     * Returns the request method, upper-case.
     *
     * @return string
     */
    public function method(): string
    {
        return $this->request->method();
    }

    /**
     * Returns the percent-decoded request path.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->request->path();
    }

    /**
     * Returns the raw query string.
     *
     * @return string
     */
    public function query(): string
    {
        return $this->request->query();
    }

    /**
     * Returns the value captured for a ":name" param of the matched route, or null.
     *
     * @param string $name Param name, without the colon.
     *
     * @return string|null
     */
    public function param(string $name): ?string
    {
        return $this->params[$name] ?? null;
    }

    /**
     * Sets the early response.
     *
     * @param Response $response The response to answer with.
     *
     * @return void
     *
     * @throws LogicException When a response is already set.
     */
    public function respond(Response $response): void
    {
        if ($this->response !== null) {
            throw new LogicException('An early response is already set.');
        }

        $this->response = $response;
    }

    /**
     * Tells whether an early response is set.
     *
     * @return boolean
     */
    public function hasResponse(): bool
    {
        return $this->response !== null;
    }

    /**
     * Returns the early response, or null when none is set.
     *
     * @return Response|null
     */
    public function response(): ?Response
    {
        return $this->response;
    }
}
