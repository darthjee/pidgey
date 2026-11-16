<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use PHPUnit\Framework\TestCase;
use Pidgey\Http\Request;

abstract class RequestTestCase extends TestCase
{
    /** @var array<string, mixed> */
    private array $savedServer = [];

    /** @var array<string, mixed> */
    private array $savedCookies = [];

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER;
        $this->savedCookies = $_COOKIE;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->savedServer;
        $_COOKIE = $this->savedCookies;
    }

    /**
     * @param array<string, string> $headers
     */
    protected function jsonRequest(string $body, array $headers = ['Content-Type' => 'application/json']): Request
    {
        return new Request(['method' => 'POST', 'uri' => '/x.json', 'headers' => $headers, 'body' => $body]);
    }
}
