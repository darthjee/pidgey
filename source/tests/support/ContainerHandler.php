<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Pidgey\Container;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;

/**
 * Test handler whose build() override keeps the container it was given.
 */
final class ContainerHandler extends RequestHandler
{
    public function __construct(public readonly Container $container)
    {
    }

    public static function build(Container $container): static
    {
        return new self($container);
    }

    protected function process(ProcessingRequest $request): Response
    {
        return new Response('container');
    }
}
