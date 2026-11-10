<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\BadRequestException;
use Pidgey\Http\HttpException;
use Pidgey\Http\NotFoundException;
use Pidgey\Http\PathId;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;

#[CoversClass(PathId::class)]
#[UsesClass(BadRequestException::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(NotFoundException::class)]
#[UsesClass(ProcessingRequest::class)]
#[UsesClass(Request::class)]
final class PathIdTest extends TestCase
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function validProvider(): array
    {
        return [
            'one' => ['1', 1],
            'several digits' => ['120', 120],
            'PHP_INT_MAX' => [(string) PHP_INT_MAX, PHP_INT_MAX],
        ];
    }

    #[DataProvider('validProvider')]
    public function testParsesAWellFormedId(string $raw, int $id): void
    {
        $this->assertSame($id, PathId::parse($this->request($raw), 'SMTP connection not found'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedProvider(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'leading zero' => ['01'],
            'decimal' => ['1.5'],
            'letters' => ['abc'],
            'empty' => [''],
            'plus sign' => ['+1'],
            'exponent' => ['1e3'],
            'hex' => ['0x1'],
            'leading space' => [' 1'],
            'trailing newline' => ["1\n"],
            'Unicode digits' => ['١٢'],
        ];
    }

    #[DataProvider('malformedProvider')]
    public function testMalformedIdIsABadRequest(string $raw): void
    {
        try {
            PathId::parse($this->request($raw), 'SMTP connection not found');
            $this->fail('Expected a BadRequestException');
        } catch (BadRequestException $error) {
            $this->assertSame(400, $error->status());
            $this->assertSame('Validation failed (numeric string is expected)', $error->errorMessage());
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function outOfRangeProvider(): array
    {
        return [
            'PHP_INT_MAX + 1' => ['9223372036854775808'],
            'huge' => [str_repeat('9', 40)],
        ];
    }

    #[DataProvider('outOfRangeProvider')]
    public function testOutOfRangeIdIsNotFound(string $raw): void
    {
        try {
            PathId::parse($this->request($raw), 'SMTP connection not found');
            $this->fail('Expected a NotFoundException');
        } catch (NotFoundException $error) {
            $this->assertSame(404, $error->status());
            $this->assertSame('SMTP connection not found', $error->errorMessage());
        }
    }

    public function testRouteWithoutIdIsALogicError(): void
    {
        $this->expectException(LogicException::class);

        PathId::parse(new ProcessingRequest(new Request(['method' => 'GET', 'uri' => '/x'])), 'Not found');
    }

    private function request(string $id): ProcessingRequest
    {
        return new ProcessingRequest(new Request(['method' => 'GET', 'uri' => '/x', 'headers' => []]), ['id' => $id]);
    }
}
