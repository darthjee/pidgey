<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\HttpException;
use Pidgey\Http\NotFoundException;

#[CoversClass(HttpException::class)]
#[CoversClass(NotFoundException::class)]
final class HttpExceptionTest extends TestCase
{
    public function testExposesItsFields(): void
    {
        $smtp = ['code' => 550, 'message' => '5.1.1 User unknown'];
        $error = new HttpException(502, 'SMTP_REJECTED', 'Recipient rejected', ['a'], $smtp);

        $this->assertSame(502, $error->status());
        $this->assertSame('SMTP_REJECTED', $error->errorCode());
        $this->assertSame('Recipient rejected', $error->errorMessage());
        $this->assertSame(['a'], $error->details());
        $this->assertSame($smtp, $error->smtp());
        $this->assertSame('Recipient rejected', $error->getMessage());
        $this->assertSame(502, $error->getCode());
    }

    public function testOptionalFieldsDefaultToNull(): void
    {
        $error = new HttpException(400);

        $this->assertNull($error->errorCode());
        $this->assertNull($error->errorMessage());
        $this->assertNull($error->details());
        $this->assertNull($error->smtp());
        $this->assertSame('', $error->getMessage());
    }

    public function testNotFoundIs404(): void
    {
        $error = new NotFoundException('Cannot GET /foo', 'GONE_FISHING');

        $this->assertSame(404, $error->status());
        $this->assertSame('Cannot GET /foo', $error->errorMessage());
        $this->assertSame('GONE_FISHING', $error->errorCode());
    }

    public function testNotFoundDefaultsToNullCodeAndMessage(): void
    {
        $error = new NotFoundException();

        $this->assertNull($error->errorCode());
        $this->assertNull($error->errorMessage());
    }
}
