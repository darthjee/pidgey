<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Http\BadRequestException;
use Pidgey\Http\HttpException;
use Pidgey\Http\PayloadTooLargeException;
use Pidgey\Http\Request;
use Pidgey\Http\UnsupportedMediaTypeException;
use Pidgey\Tests\Support\RequestTestCase;

#[CoversClass(Request::class)]
#[UsesClass(BadRequestException::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(PayloadTooLargeException::class)]
#[UsesClass(UnsupportedMediaTypeException::class)]
final class RequestCookieTest extends RequestTestCase
{
    public function testCookieIsReadFromTheGlobal(): void
    {
        $_COOKIE = ['access_token' => 'header.payload.signature'];

        $this->assertSame('header.payload.signature', (new Request())->cookie('access_token'));
    }

    public function testMissingCookieReturnsNull(): void
    {
        $_COOKIE = [];

        $this->assertNull((new Request())->cookie('access_token'));
    }

    public function testMissingCookieReturnsTheDefault(): void
    {
        $_COOKIE = ['other' => 'x'];

        $this->assertSame('fallback', (new Request())->cookie('access_token', 'fallback'));
    }

    public function testEmptyCookieReturnsEmptyStringNotTheDefault(): void
    {
        $this->assertSame('', (new Request(['cookies' => ['access_token' => '']]))->cookie('access_token', 'x'));
    }

    public function testArrayCookieReturnsTheDefault(): void
    {
        $_COOKIE = ['access_token' => ['x' => 'header.payload.signature']];

        $request = new Request();

        $this->assertNull($request->cookie('access_token'));
        $this->assertSame('fallback', $request->cookie('access_token', 'fallback'));
    }

    public function testCookiesOptionReplacesTheGlobal(): void
    {
        $_COOKIE = ['access_token' => 'from-global', 'other' => 'y'];

        $request = new Request(['cookies' => ['access_token' => 'from-option']]);

        $this->assertSame('from-option', $request->cookie('access_token'));
        $this->assertNull($request->cookie('other'));
    }

    public function testCookieLookupIsCaseSensitive(): void
    {
        $this->assertNull((new Request(['cookies' => ['access_token' => 'a']]))->cookie('Access_Token'));
    }
}
