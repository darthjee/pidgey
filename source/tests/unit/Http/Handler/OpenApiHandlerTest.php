<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Container;
use Pidgey\Http\Handler\OpenApiHandler;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use RuntimeException;

#[CoversClass(OpenApiHandler::class)]
#[UsesClass(Container::class)]
#[UsesClass(ProcessingRequest::class)]
#[UsesClass(Request::class)]
#[UsesClass(RequestHandler::class)]
#[UsesClass(Response::class)]
final class OpenApiHandlerTest extends TestCase
{
    private const DOCUMENT = __DIR__ . '/../../../../openapi/v1.json';

    public function testGetAnswersTheFileBytesAsJsonWithNoCacheHeaders(): void
    {
        $response = $this->serve('GET', new OpenApiHandler(self::DOCUMENT));

        $this->assertSame(200, $response->status());
        $this->assertSame((string) file_get_contents(self::DOCUMENT), $response->body());
        $this->assertSame(['Content-Type: application/json'], $response->headers());
    }

    public function testHeadAnswersTheSameStatusAndHeadersWithAnEmptyBody(): void
    {
        $get = $this->serve('GET', new OpenApiHandler(self::DOCUMENT));
        $head = $this->serve('HEAD', new OpenApiHandler(self::DOCUMENT));

        $this->assertSame($get->status(), $head->status());
        $this->assertSame($get->headers(), $head->headers());
        $this->assertSame('', $head->body());
    }

    public function testServesTheFileOfTheGivenPathVerbatim(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'openapi');
        $this->assertIsString($file);
        file_put_contents($file, "{\n  \"openapi\": \"3.1.0\"\n}\n");

        try {
            $response = $this->serve('GET', new OpenApiHandler($file));
        } finally {
            unlink($file);
        }

        $this->assertSame("{\n  \"openapi\": \"3.1.0\"\n}\n", $response->body());
    }

    public function testBuildServesTheCommittedDocument(): void
    {
        $handler = OpenApiHandler::build(new Container());

        $this->assertSame((string) file_get_contents(self::DOCUMENT), $this->serve('GET', $handler)->body());
    }

    public function testAnUnreadableFileFailsInsteadOfAnsweringAnEmptyDocument(): void
    {
        $this->expectException(RuntimeException::class);

        $this->serve('GET', new OpenApiHandler(__DIR__ . '/missing.json'));
    }

    private function serve(string $method, OpenApiHandler $handler): Response
    {
        return $handler->handle(
            new ProcessingRequest(new Request(['method' => $method, 'uri' => '/v1/openapi.json'])),
            [],
        );
    }
}
