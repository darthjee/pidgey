<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\ConflictException;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\HttpException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\Response;
use Pidgey\Infra\WallClock;

#[CoversClass(ConflictException::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(ErrorFormatter::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(Response::class)]
final class ConflictExceptionTest extends TestCase
{
    public function testIs409(): void
    {
        $error = new ConflictException('username is not available', 'USERNAME_TAKEN');

        $this->assertInstanceOf(HttpException::class, $error);
        $this->assertSame(409, $error->status());
        $this->assertSame('username is not available', $error->errorMessage());
        $this->assertSame('USERNAME_TAKEN', $error->errorCode());
    }

    public function testDefaultsToNullCodeAndMessage(): void
    {
        $error = new ConflictException();

        $this->assertNull($error->errorCode());
        $this->assertNull($error->errorMessage());
    }

    public function testFormatterRendersTheSpecificCode(): void
    {
        $response = $this->formatter()->format(new ConflictException('username is not available', 'USERNAME_TAKEN'));

        $this->assertSame(409, $response->status());
        $this->assertSame(
            '{"error":{"code":"USERNAME_TAKEN","message":"username is not available"},'
            . '"statusCode":409,"timestamp":"2026-10-25T12:00:00.000Z"}',
            $response->body(),
        );
    }

    public function testFormatterFallsBackToTheConflictCode(): void
    {
        $response = $this->formatter()->format(new ConflictException());

        $this->assertSame(
            '{"error":{"code":"CONFLICT","message":"Conflict"},'
            . '"statusCode":409,"timestamp":"2026-10-25T12:00:00.000Z"}',
            $response->body(),
        );
    }

    private function formatter(): ErrorFormatter
    {
        $clock = new class implements WallClock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-25T12:00:00.000Z');
            }
        };

        return new ErrorFormatter($clock);
    }
}
