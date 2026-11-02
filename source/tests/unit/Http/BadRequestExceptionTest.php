<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\BadRequestException;
use Pidgey\Http\HttpException;

#[CoversClass(BadRequestException::class)]
#[UsesClass(HttpException::class)]
final class BadRequestExceptionTest extends TestCase
{
    public function testIs400(): void
    {
        $error = new BadRequestException('Validation failed (numeric string is expected)', 'NOPE');

        $this->assertInstanceOf(HttpException::class, $error);
        $this->assertSame(400, $error->status());
        $this->assertSame('Validation failed (numeric string is expected)', $error->errorMessage());
        $this->assertSame('NOPE', $error->errorCode());
        $this->assertNull($error->details());
    }

    public function testDefaultsToNullCodeAndMessage(): void
    {
        $error = new BadRequestException();

        $this->assertNull($error->errorCode());
        $this->assertNull($error->errorMessage());
    }
}
