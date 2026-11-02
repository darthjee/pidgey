<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\ForbiddenException;
use Pidgey\Http\HttpException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\Response;
use Pidgey\Infra\WallClock;

#[CoversClass(ForbiddenException::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(ErrorFormatter::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(Response::class)]
final class ForbiddenExceptionTest extends TestCase
{
    public function testIs403(): void
    {
        $error = new ForbiddenException('Cross-site request rejected', 'NOPE');

        $this->assertInstanceOf(HttpException::class, $error);
        $this->assertSame(403, $error->status());
        $this->assertSame('Cross-site request rejected', $error->errorMessage());
        $this->assertSame('NOPE', $error->errorCode());
    }

    public function testDefaultsToNullCodeAndMessage(): void
    {
        $error = new ForbiddenException();

        $this->assertNull($error->errorCode());
        $this->assertNull($error->errorMessage());
    }

    public function testFormatterRendersTheForbiddenCode(): void
    {
        $clock = new class implements WallClock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-25T12:00:00.000Z');
            }
        };
        $formatter = new ErrorFormatter($clock);

        $response = $formatter->format(new ForbiddenException('Cross-site request rejected'));

        $this->assertSame(403, $response->status());
        $this->assertSame(
            '{"error":{"code":"FORBIDDEN","message":"Cross-site request rejected"},'
            . '"statusCode":403,"timestamp":"2026-10-25T12:00:00.000Z"}',
            $response->body(),
        );
    }
}
