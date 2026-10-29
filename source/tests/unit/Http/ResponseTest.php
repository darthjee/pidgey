<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\Response;

#[CoversClass(Response::class)]
final class ResponseTest extends TestCase
{
    public function testDefaults(): void
    {
        $response = new Response();

        $this->assertSame('', $response->body());
        $this->assertSame(200, $response->status());
        $this->assertSame([], $response->headers());
    }

    public function testConstructorValues(): void
    {
        $response = new Response('hello', 418, ['X-A: 1', 'X-B: 2', 'X-A: 3']);

        $this->assertSame('hello', $response->body());
        $this->assertSame(418, $response->status());
        $this->assertSame(['X-A: 1', 'X-B: 2', 'X-A: 3'], $response->headers());
    }

    public function testAddHeaderAppends(): void
    {
        $response = new Response('', 200, ['X-A: 1']);

        $response->addHeader('X-B: 2');
        $response->addHeader('X-A: 3');

        $this->assertSame(['X-A: 1', 'X-B: 2', 'X-A: 3'], $response->headers());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function injectionProvider(): array
    {
        return [
            'carriage return' => ["X-A: 1\rX-Evil: 2"],
            'line feed' => ["X-A: 1\nX-Evil: 2"],
            'both' => ["X-A: 1\r\nX-Evil: 2"],
        ];
    }

    #[DataProvider('injectionProvider')]
    public function testAddHeaderRejectsLineBreaks(string $line): void
    {
        $response = new Response();

        $this->expectException(InvalidArgumentException::class);

        $response->addHeader($line);
    }

    #[DataProvider('injectionProvider')]
    public function testConstructorRejectsLineBreaks(string $line): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Response('', 200, [$line]);
    }
}
