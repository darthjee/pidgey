<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\HttpException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\LockedException;
use Pidgey\Http\Response;
use Pidgey\Infra\WallClock;

#[CoversClass(LockedException::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(ErrorFormatter::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(Response::class)]
final class LockedExceptionTest extends TestCase
{
    private const MESSAGE = 'Account temporarily locked due to too many failed attempts';

    public function testIs423(): void
    {
        $error = new LockedException(self::MESSAGE, 'LOCKED');

        $this->assertInstanceOf(HttpException::class, $error);
        $this->assertSame(423, $error->status());
        $this->assertSame(self::MESSAGE, $error->errorMessage());
        $this->assertSame('LOCKED', $error->errorCode());
    }

    public function testDefaultsToNullCodeAndMessage(): void
    {
        $error = new LockedException();

        $this->assertNull($error->errorCode());
        $this->assertNull($error->errorMessage());
    }

    public function testFormatterRendersTheMessage(): void
    {
        $response = $this->formatter()->format(new LockedException(self::MESSAGE));

        $this->assertSame(423, $response->status());
        $this->assertSame(
            '{"error":{"code":"LOCKED","message":"' . self::MESSAGE . '"},'
            . '"statusCode":423,"timestamp":"2026-10-25T12:00:00.000Z"}',
            $response->body(),
        );
    }

    public function testFormatterFallsBackToTheLockedCodeAndMessage(): void
    {
        $response = $this->formatter()->format(new LockedException());

        $this->assertSame(
            '{"error":{"code":"LOCKED","message":"Locked"},'
            . '"statusCode":423,"timestamp":"2026-10-25T12:00:00.000Z"}',
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
