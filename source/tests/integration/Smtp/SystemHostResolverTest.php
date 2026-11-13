<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Smtp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Smtp\SystemHostResolver;

#[CoversClass(SystemHostResolver::class)]
final class SystemHostResolverTest extends TestCase
{
    public function testResolvesLocalhostThroughTheHostsFile(): void
    {
        $addresses = (new SystemHostResolver())->resolve('localhost');

        $this->assertContains('127.0.0.1', $addresses);
        $this->assertSame(array_values(array_unique($addresses)), $addresses);
    }

    public function testReturnsTheIpv4AddressesFirst(): void
    {
        $addresses = (new SystemHostResolver())->resolve('localhost');

        $this->assertNotFalse(filter_var($addresses[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4));
    }

    public function testAnUnknownHostResolvesToNothing(): void
    {
        $this->assertSame([], (new SystemHostResolver())->resolve('pidgey-no-such-host.invalid'));
    }
}
