<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\Handler\StaticFileHandler;
use Pidgey\Http\HttpException;
use Pidgey\Http\NotFoundException;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;

#[CoversClass(StaticFileHandler::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(NotFoundException::class)]
#[UsesClass(ProcessingRequest::class)]
#[UsesClass(Request::class)]
#[UsesClass(RequestHandler::class)]
#[UsesClass(Response::class)]
final class StaticFileHandlerTest extends TestCase
{
    private const ASSET_CACHE = 'Cache-Control: public, max-age=31536000, immutable';

    private string $fixture;

    private ?string $tempDir = null;

    /** @var list<string> */
    private array $logged = [];

    protected function setUp(): void
    {
        $this->fixture = dirname(__DIR__, 3) . '/fixtures/static';
        $this->logged = [];
    }

    protected function tearDown(): void
    {
        if ($this->tempDir !== null) {
            $this->removeTree($this->tempDir);
            $this->tempDir = null;
        }
    }

    public function testServesTheIndexWithItsHeaders(): void
    {
        $body = (string) file_get_contents($this->fixture . '/index.html');

        $response = $this->serve('/');

        $this->assertSame(200, $response->status());
        $this->assertSame($body, $response->body());
        $this->assertSame([
            'Content-Type: text/html; charset=utf-8',
            'Content-Length: ' . strlen($body),
            'X-Content-Type-Options: nosniff',
            'Cache-Control: no-cache',
            "Content-Security-Policy: frame-ancestors 'none'",
            'X-Frame-Options: DENY',
        ], $response->headers());
        $this->assertSame([], $this->logged);
    }

    public function testIgnoresTheQueryStringOnTheIndex(): void
    {
        $this->assertSame(200, $this->serve('/?x=1')->status());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function assetProvider(): array
    {
        return [
            'javascript' => ['/assets/app.js', 'text/javascript; charset=utf-8'],
            'stylesheet' => ['/assets/app.css', 'text/css; charset=utf-8'],
            'woff font' => ['/assets/font.woff', 'font/woff'],
            'woff2 font' => ['/assets/font.woff2', 'font/woff2'],
            'upper-case extension' => ['/assets/LOUD.JS', 'text/javascript; charset=utf-8'],
            'nested file' => ['/assets/dir/nested.js', 'text/javascript; charset=utf-8'],
        ];
    }

    #[DataProvider('assetProvider')]
    public function testServesAnAssetWithItsHeaders(string $path, string $contentType): void
    {
        $body = (string) file_get_contents($this->fixture . $path);

        $response = $this->serve($path);

        $this->assertSame(200, $response->status());
        $this->assertSame($body, $response->body());
        $this->assertSame([
            'Content-Type: ' . $contentType,
            'Content-Length: ' . strlen($body),
            'X-Content-Type-Options: nosniff',
            self::ASSET_CACHE,
        ], $response->headers());
        $this->assertSame([], $this->logged);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedProvider(): array
    {
        return [
            'missing file' => ['/assets/missing.js'],
            'directory' => ['/assets/dir'],
            'directory with slash' => ['/assets/dir/'],
            'parent segment' => ['/assets/../secret.html'],
            'backslash' => ['/assets/a\\b.js'],
            'dotfile' => ['/assets/.hidden.js'],
            'disallowed extension' => ['/assets/notes.txt'],
        ];
    }

    #[DataProvider('rejectedProvider')]
    public function testRejectsWithTheJsonNotFound(string $path): void
    {
        $this->assertNotFound($this->fixture, $path, $this->fixture . '/' . ltrim($path, '/'));
    }

    public function testRejectsASymlinkEscapingTheBaseFolder(): void
    {
        $base = $this->makeTempDir() . '/static';
        mkdir($base . '/assets', 0777, true);
        file_put_contents($this->tempDir . '/outside.js', 'outside');
        symlink($this->tempDir . '/outside.js', $base . '/assets/escape.js');

        $this->assertNotFound($base, '/assets/escape.js', $base . '/assets/escape.js');
    }

    public function testRejectsAnUnreadableFile(): void
    {
        $base = $this->makeTempDir();
        mkdir($base . '/assets');
        file_put_contents($base . '/assets/locked.js', 'locked');
        chmod($base . '/assets/locked.js', 0000);

        $this->assertNotFound($base, '/assets/locked.js', $base . '/assets/locked.js');
    }

    public function testRejectsTheIndexWhenTheBuildIsMissing(): void
    {
        $base = $this->makeTempDir();

        $this->assertNotFound($base, '/', $base . '/index.html');
    }

    public function testRejectsTheIndexWhenTheBaseFolderIsMissing(): void
    {
        $base = $this->makeTempDir() . '/static';

        $this->assertNotFound($base, '/', $base . '/index.html');
    }

    public function testBuildsWithTheDefaults(): void
    {
        $this->assertInstanceOf(RequestHandler::class, new StaticFileHandler());
    }

    private function serve(string $uri, ?string $baseDir = null): Response
    {
        $handler = new StaticFileHandler($baseDir ?? $this->fixture, function (string $line): void {
            $this->logged[] = $line;
        });

        return $handler->handle(new ProcessingRequest(new Request(['method' => 'GET', 'uri' => $uri])), []);
    }

    private function assertNotFound(string $baseDir, string $path, string $resolved): void
    {
        try {
            $this->serve($path, $baseDir);
            $this->fail('Expected a NotFoundException.');
        } catch (NotFoundException $error) {
            $this->assertSame('Cannot GET ' . $path, $error->getMessage());
            $this->assertSame(404, $error->status());
        }

        $this->assertCount(1, $this->logged);
        $this->assertStringContainsString($resolved, $this->logged[0]);
    }

    private function makeTempDir(): string
    {
        $this->tempDir = sys_get_temp_dir() . '/pidgey-static-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir);

        return $this->tempDir;
    }

    private function removeTree(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;

            if (is_dir($path) && !is_link($path)) {
                $this->removeTree($path);
                continue;
            }

            unlink($path);
        }

        rmdir($dir);
    }
}
