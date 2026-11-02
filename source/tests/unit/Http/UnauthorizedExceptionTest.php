<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\UnauthorizedException;
use Pidgey\Http\HttpException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\Response;
use Pidgey\Infra\WallClock;

#[CoversClass(UnauthorizedException::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(ErrorFormatter::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(Response::class)]
final class UnauthorizedExceptionTest extends TestCase
{
    public function testIs401(): void
    {
        $error = new UnauthorizedException('Invalid or expired access token', 'NOPE');

        $this->assertInstanceOf(HttpException::class, $error);
        $this->assertSame(401, $error->status());
        $this->assertSame('Invalid or expired access token', $error->errorMessage());
        $this->assertSame('NOPE', $error->errorCode());
    }

    public function testDefaultsToNullCodeAndMessage(): void
    {
        $error = new UnauthorizedException();

        $this->assertNull($error->errorCode());
        $this->assertNull($error->errorMessage());
    }

    public function testFormatterRendersTheUnauthorizedEnvelope(): void
    {
        $clock = new class implements WallClock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-25T12:00:00.000Z');
            }
        };
        $formatter = new ErrorFormatter($clock);

        $response = $formatter->format(new UnauthorizedException('Invalid or expired access token'));

        $this->assertSame(401, $response->status());
        $this->assertSame(
            '{"error":{"code":"UNAUTHORIZED","message":"Invalid or expired access token"},'
            . '"statusCode":401,"timestamp":"2026-10-25T12:00:00.000Z"}',
            $response->body(),
        );
        $this->assertSame(['Content-Type: application/json'], $response->headers());
    }

    public function testFormatterFallsBackToTheReasonPhrase(): void
    {
        $response = (new ErrorFormatter())->format(new UnauthorizedException());

        $this->assertSame(401, $response->status());
        $this->assertSame(
            ['code' => 'UNAUTHORIZED', 'message' => 'Unauthorized'],
            json_decode($response->body(), true)['error'],
        );
    }
}
