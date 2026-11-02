<?php

declare(strict_types=1);

namespace Pidgey\Http;

use LogicException;

/**
 * Per-dispatch wrapper around the Request, carrying the route params, the attached data and
 * the early response.
 */
final class ProcessingRequest
{
    private ?Response $response = null;

    /** @var array<class-string, object> Attached objects, keyed by their class. */
    private array $attachments = [];

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
     * Returns the raw request URI, never decoded.
     *
     * @return string
     */
    public function uri(): string
    {
        return $this->request->uri();
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
     * Attaches an object, keyed by its class.
     *
     * @param object $value The object to attach.
     *
     * @return void
     *
     * @throws LogicException When an object of the same class is already attached.
     */
    public function attach(object $value): void
    {
        $class = $value::class;

        if (array_key_exists($class, $this->attachments)) {
            throw new LogicException("An object of class {$class} is already attached.");
        }

        $this->attachments[$class] = $value;
    }

    /**
     * Returns the attached object that is an instance of the class or interface, or null.
     *
     * @template T of object
     *
     * @param string $class Class or interface to look up.
     *
     * @phpstan-param class-string<T> $class
     *
     * @return T|null
     *
     * @throws LogicException When more than one attached object matches.
     */
    public function attached(string $class): ?object
    {
        $matches = array_values(array_filter(
            $this->attachments,
            static fn (object $value): bool => $value instanceof $class,
        ));

        if (count($matches) > 1) {
            throw new LogicException("More than one attached object matches {$class}.");
        }

        return $matches[0] ?? null;
    }

    /**
     * Returns the attached object that is an instance of the class or interface.
     *
     * A missing object is a route or handler bug, so it answers 500, never 401.
     *
     * @template T of object
     *
     * @param string $class Class or interface to look up.
     *
     * @phpstan-param class-string<T> $class
     *
     * @return T
     *
     * @throws LogicException When no attached object matches, or more than one does.
     */
    public function require(string $class): object
    {
        return $this->attached($class) ?? throw new LogicException("No attached object matches {$class}.");
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
