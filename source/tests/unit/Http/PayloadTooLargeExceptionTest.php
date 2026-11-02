<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\HttpException;
use Pidgey\Http\PayloadTooLargeException;

#[CoversClass(PayloadTooLargeException::class)]
#[UsesClass(HttpException::class)]
final class PayloadTooLargeExceptionTest extends TestCase
{
    public function testIs413WithoutCodeNorMessage(): void
    {
        $error = new PayloadTooLargeException();

        $this->assertInstanceOf(HttpException::class, $error);
        $this->assertSame(413, $error->status());
        $this->assertNull($error->errorCode());
        $this->assertNull($error->errorMessage());
        $this->assertNull($error->details());
    }
}
