<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Auth;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\AdminCheck;
use Pidgey\Auth\AuthenticatedUser;
use Pidgey\Http\BaseMiddleware;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\ForbiddenException;
use Pidgey\Http\HttpException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;
use Pidgey\Http\Response;
use Pidgey\Infra\WallClock;
use Psr\Log\NullLogger;
use Throwable;

#[CoversClass(AdminCheck::class)]
#[UsesClass(AuthenticatedUser::class)]
#[UsesClass(BaseMiddleware::class)]
#[UsesClass(ErrorFormatter::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(ForbiddenException::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(ProcessingRequest::class)]
#[UsesClass(Request::class)]
#[UsesClass(Response::class)]
final class AdminCheckTest extends TestCase
{
    private const SESSION = '0b8f2c3e-1d4a-4b6c-9e7f-a1b2c3d4e5f6';

    public const TIMESTAMP = '2026-10-25T12:00:00.000Z';

    public function testAnAdminPasses(): void
    {
        $request = $this->processing();
        $request->attach(new AuthenticatedUser(1, self::SESSION, 'root', true, 1));

        (new AdminCheck())->processRequest($request);

        $this->assertFalse($request->hasResponse());
    }

    public function testANonAdminGets403(): void
    {
        $request = $this->processing();
        $request->attach(new AuthenticatedUser(7, self::SESSION, 'bob', false, 1));

        $response = $this->format(static fn () => (new AdminCheck())->processRequest($request));

        $this->assertSame(403, $response->status());
        $this->assertSame(
            '{"error":{"code":"FORBIDDEN","message":"Admin access required"},'
            . '"statusCode":403,"timestamp":"' . self::TIMESTAMP . '"}',
            $response->body(),
        );
    }

    public function testWithoutTheGateItThrowsALogicExceptionThatGives500(): void
    {
        $request = $this->processing();

        try {
            (new AdminCheck())->processRequest($request);
            $this->fail('Expected a LogicException');
        } catch (LogicException $error) {
            $response = $this->formatter()->format($error);
        }

        $this->assertSame(500, $response->status());
        $this->assertSame('INTERNAL_ERROR', json_decode($response->body(), true)['error']['code']);
    }

    private function processing(): ProcessingRequest
    {
        return new ProcessingRequest(new Request(['method' => 'GET', 'uri' => '/admin/users.json']));
    }

    private function format(callable $action): Response
    {
        try {
            $action();
        } catch (Throwable $error) {
            return $this->formatter()->format($error);
        }

        $this->fail('Expected an exception');
    }

    private function formatter(): ErrorFormatter
    {
        $clock = new class implements WallClock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable(AdminCheckTest::TIMESTAMP);
            }
        };

        return new ErrorFormatter($clock, new NullLogger());
    }
}
