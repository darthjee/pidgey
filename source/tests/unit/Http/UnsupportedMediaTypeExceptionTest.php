<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\HttpException;
use Pidgey\Http\UnsupportedMediaTypeException;

#[CoversClass(UnsupportedMediaTypeException::class)]
#[UsesClass(HttpException::class)]
final class UnsupportedMediaTypeExceptionTest extends TestCase
{
    public function testIs415WithoutCodeNorMessage(): void
    {
        $error = new UnsupportedMediaTypeException();

        $this->assertInstanceOf(HttpException::class, $error);
        $this->assertSame(415, $error->status());
        $this->assertNull($error->errorCode());
        $this->assertNull($error->errorMessage());
        $this->assertNull($error->details());
    }
}
