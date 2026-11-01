<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\HttpException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\NotFoundException;
use Pidgey\Http\Response;
use Pidgey\Infra\SystemWallClock;
use Pidgey\Infra\WallClock;

#[CoversClass(ErrorFormatter::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(NotFoundException::class)]
#[UsesClass(Response::class)]
#[UsesClass(SystemWallClock::class)]
final class ErrorFormatterTest extends TestCase
{
    /** @var list<string> */
    private array $logged = [];

    private ErrorFormatter $formatter;

    protected function setUp(): void
    {
        $clock = new class implements WallClock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-25T12:00:00.000Z');
            }
        };

        $this->formatter = new ErrorFormatter($clock, function (string $line): void {
            $this->logged[] = $line;
        });
    }

    public function testFormatsNotFound(): void
    {
        $response = $this->formatter->format(new NotFoundException('Cannot GET /foo.json'));

        $this->assertSame(404, $response->status());
        $this->assertSame(
            '{"error":{"code":"NOT_FOUND","message":"Cannot GET /foo.json"},'
            . '"statusCode":404,"timestamp":"2026-10-25T12:00:00.000Z"}',
            $response->body(),
        );
        $this->assertSame([], $this->logged);
    }

    public function testUsesTheGivenFields(): void
    {
        $smtp = ['code' => 550, 'message' => 'no'];
        $response = $this->formatter->format(new HttpException(502, 'SMTP_REJECTED', 'Rejected', ['x'], $smtp));

        $this->assertSame(
            ['code' => 'SMTP_REJECTED', 'message' => 'Rejected', 'details' => ['x'], 'smtp' => $smtp],
            $this->decode($response)['error'],
        );
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function fallbacks(): array
    {
        return [
            '400' => [400, 'BAD_REQUEST', 'Bad Request'],
            '401' => [401, 'UNAUTHORIZED', 'Unauthorized'],
            '403' => [403, 'FORBIDDEN', 'Forbidden'],
            '404' => [404, 'NOT_FOUND', 'Not Found'],
            '409' => [409, 'CONFLICT', 'Conflict'],
            '413' => [413, 'HTTP_413', 'Content Too Large'],
            '415' => [415, 'HTTP_415', 'Unsupported Media Type'],
            '422' => [422, 'HTTP_422', 'Unprocessable Content'],
            '423' => [423, 'LOCKED', 'Locked'],
            '429' => [429, 'TOO_MANY_REQUESTS', 'Too Many Requests'],
            '500' => [500, 'INTERNAL_ERROR', 'Internal Server Error'],
            'unknown status' => [418, 'HTTP_418', 'HTTP 418'],
        ];
    }

    #[DataProvider('fallbacks')]
    public function testFallsBackToTheCategoryCodeAndReasonPhrase(int $status, string $code, string $message): void
    {
        $response = $this->formatter->format(new HttpException($status));

        $this->assertSame($status, $response->status());
        $this->assertSame(['code' => $code, 'message' => $message], $this->decode($response)['error']);
        $this->assertSame($status, $this->decode($response)['statusCode']);
    }

    public function testAnyOtherThrowableIsAnInternalErrorAndIsLogged(): void
    {
        $response = $this->formatter->format(new LogicException('secret detail'));

        $this->assertSame(500, $response->status());
        $this->assertSame(
            '{"error":{"code":"INTERNAL_ERROR","message":"Internal server error"},'
            . '"statusCode":500,"timestamp":"2026-10-25T12:00:00.000Z"}',
            $response->body(),
        );
        $this->assertStringNotContainsString('secret', $response->body());
        $this->assertCount(1, $this->logged);
        $this->assertStringContainsString('LogicException', $this->logged[0]);
        $this->assertStringContainsString('secret detail', $this->logged[0]);
    }

    public function testDefaultsToTheSystemClock(): void
    {
        $response = (new ErrorFormatter())->format(new NotFoundException());

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/',
            $this->decode($response)['timestamp'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        return json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);
    }
}
