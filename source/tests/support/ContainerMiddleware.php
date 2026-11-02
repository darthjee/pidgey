<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Pidgey\Container;
use Pidgey\Http\BaseMiddleware;

/**
 * Test middleware whose build() override keeps the container it was given.
 */
final class ContainerMiddleware extends BaseMiddleware
{
    public function __construct(public readonly Container $container)
    {
    }

    public static function build(Container $container): static
    {
        return new self($container);
    }
}
