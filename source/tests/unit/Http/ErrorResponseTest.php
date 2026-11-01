<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\Response;

#[CoversClass(ErrorResponse::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(Response::class)]
final class ErrorResponseTest extends TestCase
{
    private DateTimeImmutable $time;

    protected function setUp(): void
    {
        $this->time = new DateTimeImmutable('2026-10-25T12:00:00.123456Z');
    }

    public function testBuildsTheEnvelopeWithoutOptionalKeys(): void
    {
        $response = new ErrorResponse(404, 'NOT_FOUND', 'Cannot GET /foo', null, null, $this->time);

        $this->assertSame(
            '{"error":{"code":"NOT_FOUND","message":"Cannot GET /foo"},'
            . '"statusCode":404,"timestamp":"2026-10-25T12:00:00.123Z"}',
            $response->body(),
        );
        $this->assertSame(404, $response->status());
        $this->assertSame(['Content-Type: application/json'], $response->headers());
    }

    public function testIncludesDetailsAndSmtpWhenGiven(): void
    {
        $response = new ErrorResponse(
            502,
            'SMTP_REJECTED',
            'Recipient rejected',
            ['a', 'b'],
            ['code' => 550, 'message' => '5.1.1 User unknown'],
            $this->time,
        );

        $this->assertSame(
            [
                'error' => [
                    'code' => 'SMTP_REJECTED',
                    'message' => 'Recipient rejected',
                    'details' => ['a', 'b'],
                    'smtp' => ['code' => 550, 'message' => '5.1.1 User unknown'],
                ],
                'statusCode' => 502,
                'timestamp' => '2026-10-25T12:00:00.123Z',
            ],
            json_decode($response->body(), true),
        );
    }

    public function testConvertsTheTimestampToUtc(): void
    {
        $time = new DateTimeImmutable('2026-10-25 09:00:00.5', new DateTimeZone('America/Sao_Paulo'));
        $response = new ErrorResponse(500, 'INTERNAL_ERROR', 'Internal server error', null, null, $time);

        $this->assertSame('2026-10-25T12:00:00.500Z', json_decode($response->body(), true)['timestamp']);
    }
}
