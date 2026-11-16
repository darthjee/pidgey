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
final class RequestMethodTest extends RequestTestCase
{
    public function testMethodIsUpperCased(): void
    {
        $this->assertSame('POST', (new Request(['method' => 'post']))->method());
    }

    public function testMethodDefaultsToGet(): void
    {
        unset($_SERVER['REQUEST_METHOD']);

        $this->assertSame('GET', (new Request())->method());
    }

    public function testMethodFallsBackToServerGlobal(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'delete';

        $this->assertSame('DELETE', (new Request())->method());
    }

    public function testUriFallsBackToServerGlobal(): void
    {
        $_SERVER['REQUEST_URI'] = '/from/server?x=1';

        $request = new Request();

        $this->assertSame('/from/server', $request->path());
        $this->assertSame('x=1', $request->query());
    }

    public function testOptionsWinOverServerGlobals(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI'] = '/from/server';

        $request = new Request(['method' => 'put', 'uri' => '/from/option']);

        $this->assertSame('PUT', $request->method());
        $this->assertSame('/from/option', $request->path());
    }
}
