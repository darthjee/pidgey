<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use JsonException;
use JsonSerializable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\Response;

#[CoversClass(JsonResponse::class)]
#[UsesClass(Response::class)]
final class JsonResponseTest extends TestCase
{
    public function testEncodesTheData(): void
    {
        $this->assertSame('{"status":"ok"}', (new JsonResponse(['status' => 'ok']))->body());
    }

    public function testLeavesSlashesAndUnicodeUnescaped(): void
    {
        $response = new JsonResponse(['path' => '/a/b', 'name' => 'Pidgéy ✉']);

        $this->assertSame('{"path":"/a/b","name":"Pidgéy ✉"}', $response->body());
    }

    public function testStatusDefaultsTo200(): void
    {
        $this->assertSame(200, (new JsonResponse([]))->status());
    }

    public function testStatusCanBeSet(): void
    {
        $this->assertSame(201, (new JsonResponse(['id' => 1], 201))->status());
    }

    public function testAddsTheJsonContentType(): void
    {
        $this->assertSame(['Content-Type: application/json'], (new JsonResponse([]))->headers());
    }

    public function testEncodesJsonSerializable(): void
    {
        $data = new class implements JsonSerializable {
            /**
             * @return array<string, string>
             */
            public function jsonSerialize(): array
            {
                return ['status' => 'ok'];
            }
        };

        $this->assertSame('{"status":"ok"}', (new JsonResponse($data))->body());
    }

    public function testKeysAreNotConverted(): void
    {
        $response = new JsonResponse(['snake_case' => 1, 'camelCase' => 2]);

        $this->assertSame('{"snake_case":1,"camelCase":2}', $response->body());
    }

    public function testInvalidUtf8Throws(): void
    {
        $this->expectException(JsonException::class);

        new JsonResponse(['bad' => "\xB1\x31"]);
    }
}
