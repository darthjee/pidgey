<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Auth;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\AuthenticatedApiToken;
use Pidgey\Auth\RequireScope;
use Pidgey\Auth\RequireSendScope;
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

#[CoversClass(RequireScope::class)]
#[CoversClass(RequireSendScope::class)]
#[UsesClass(AuthenticatedApiToken::class)]
#[UsesClass(BaseMiddleware::class)]
#[UsesClass(ErrorFormatter::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(ForbiddenException::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(ProcessingRequest::class)]
#[UsesClass(Request::class)]
#[UsesClass(Response::class)]
final class RequireScopeTest extends TestCase
{
    public const TIMESTAMP = '2026-11-22T12:00:00.000Z';

    public function testATokenWithTheSendScopePasses(): void
    {
        $request = $this->processing();
        $request->attach(new AuthenticatedApiToken(1, 1, ['send']));

        (new RequireSendScope())->processRequest($request);

        $this->assertFalse($request->hasResponse());
    }

    public function testATokenWithoutTheScopeGets403(): void
    {
        $request = $this->processing();
        $request->attach(new AuthenticatedApiToken(1, 1, ['other']));

        $response = $this->format(static fn () => (new RequireSendScope())->processRequest($request));

        $this->assertSame(403, $response->status());
        $this->assertSame(
            '{"error":{"code":"FORBIDDEN","message":"The API token lacks the required scope"},'
            . '"statusCode":403,"timestamp":"' . self::TIMESTAMP . '"}',
            $response->body(),
        );
    }

    public function testATokenWithNoScopesGets403(): void
    {
        $request = $this->processing();
        $request->attach(new AuthenticatedApiToken(1, 1, []));

        $response = $this->format(static fn () => (new RequireSendScope())->processRequest($request));

        $this->assertSame(403, $response->status());
    }

    public function testWithoutApiTokenAuthItThrowsALogicExceptionThatGives500(): void
    {
        try {
            (new RequireSendScope())->processRequest($this->processing());
            $this->fail('Expected a LogicException');
        } catch (LogicException $error) {
            $response = $this->formatter()->format($error);
        }

        $this->assertSame(500, $response->status());
        $this->assertSame('INTERNAL_ERROR', json_decode($response->body(), true)['error']['code']);
    }

    private function processing(): ProcessingRequest
    {
        return new ProcessingRequest(new Request(['method' => 'POST', 'uri' => '/v1/messages']));
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
                return new DateTimeImmutable(RequireScopeTest::TIMESTAMP);
            }
        };

        return new ErrorFormatter($clock, new NullLogger());
    }
}
