<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Smtp;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Pidgey\Smtp\FailureCode;

#[CoversNothing]
final class FailureCodeTest extends TestCase
{
    public function testValuesAreTheSevenTransportCodes(): void
    {
        $this->assertSame(
            [
                'SMTP_CONNECTION_FAILED',
                'SMTP_TLS_FAILED',
                'SMTP_AUTH_FAILED',
                'SMTP_REJECTED',
                'SMTP_TEMPORARY_FAILURE',
                'SMTP_PROTOCOL_ERROR',
                'SMTP_TIMEOUT',
            ],
            array_map(static fn (FailureCode $code): string => $code->value, FailureCode::cases()),
        );
    }
}
