<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\Response;
use Pidgey\Http\ResponseSender;

#[CoversClass(ResponseSender::class)]
#[UsesClass(Response::class)]
final class ResponseSenderTest extends TestCase
{
    /** @var list<int> */
    private array $statuses = [];

    /** @var list<string> */
    private array $lines = [];

    public function testSendsStatusHeadersAndBody(): void
    {
        $response = new Response('{"status":"ok"}', 201, [
            'Content-Type: application/json',
            'Set-Cookie: a=1',
            'Set-Cookie: b=2',
        ]);

        $this->expectOutputString('{"status":"ok"}');

        $this->sender()->send($response);

        $this->assertSame([201], $this->statuses);
        $this->assertSame(
            ['Content-Type: application/json', 'Set-Cookie: a=1', 'Set-Cookie: b=2'],
            $this->lines,
        );
    }

    public function testSendsAResponseWithoutHeadersOrBody(): void
    {
        $this->expectOutputString('');

        $this->sender()->send(new Response('', 204));

        $this->assertSame([204], $this->statuses);
        $this->assertSame([], $this->lines);
    }

    public function testBuildsWithDefaultEmitters(): void
    {
        $this->assertInstanceOf(ResponseSender::class, new ResponseSender());
    }

    private function sender(): ResponseSender
    {
        return new ResponseSender(
            function (int $status): void {
                $this->statuses[] = $status;
            },
            function (string $line): void {
                $this->lines[] = $line;
            },
        );
    }
}
