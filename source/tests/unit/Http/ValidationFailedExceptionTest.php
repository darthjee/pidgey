<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\HttpException;
use Pidgey\Http\ValidationFailedException;

#[CoversClass(ValidationFailedException::class)]
#[UsesClass(HttpException::class)]
final class ValidationFailedExceptionTest extends TestCase
{
    public function testIs400ValidationFailed(): void
    {
        $details = ['username should not be empty', 'password must be longer than or equal to 8 characters'];
        $error = new ValidationFailedException($details);

        $this->assertInstanceOf(HttpException::class, $error);
        $this->assertSame(400, $error->status());
        $this->assertSame('VALIDATION_FAILED', $error->errorCode());
        $this->assertSame(
            'username should not be empty; password must be longer than or equal to 8 characters',
            $error->errorMessage(),
        );
        $this->assertSame($details, $error->details());
    }

    public function testSingleDetailIsTheMessage(): void
    {
        $error = new ValidationFailedException(['email must be an email']);

        $this->assertSame('email must be an email', $error->errorMessage());
        $this->assertSame(['email must be an email'], $error->details());
    }
}
