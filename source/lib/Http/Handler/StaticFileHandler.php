<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use Closure;
use Pidgey\Http\NotFoundException;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;

/**
 * Serves the built frontend: "/" gives index.html and "/assets/<rest>" gives the hashed bundles.
 *
 * Files come from a base folder fixed by the source tree (source/static/), never from the request.
 * Traversal, NUL bytes, backslashes, dotfiles, symlink escapes, missing files, directories and
 * extensions off the allow-list all give the router's JSON 404; the resolved path is logged
 * (control characters escaped), never sent.
 */
final class StaticFileHandler extends RequestHandler
{
    private const INDEX_FILE = 'index.html';

    /** @var array<string, string> Allowed extension (lower-case) => Content-Type. */
    private const CONTENT_TYPES = [
        'html' => 'text/html; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    /** @var list<string> */
    private const INDEX_HEADERS = [
        'Cache-Control: no-cache',
        "Content-Security-Policy: frame-ancestors 'none'",
        'X-Frame-Options: DENY',
    ];

    /** @var list<string> */
    private const ASSET_HEADERS = [
        'Cache-Control: public, max-age=31536000, immutable',
    ];

    private readonly string $baseDir;

    private readonly Closure $logger;

    /**
     * Builds the handler.
     *
     * @param string|null   $baseDir Folder the files are served from; defaults to source/static.
     * @param callable|null $logger  Receives the log line of each 404; defaults to error_log().
     */
    public function __construct(?string $baseDir = null, ?callable $logger = null)
    {
        $this->baseDir = $baseDir ?? dirname(__DIR__, 3) . '/static';
        $this->logger = $logger === null
            ? static fn (string $line) => error_log($line)
            : Closure::fromCallable($logger);
    }

    /**
     * Serves the file mapped from the request path, or throws the JSON 404.
     *
     * @param ProcessingRequest $request The request being dispatched.
     *
     * @return Response
     *
     * @throws NotFoundException When the path is rejected, or the file is missing or not allowed.
     */
    protected function process(ProcessingRequest $request): Response
    {
        $path = $request->path();
        $isIndex = $path === '/';
        $relative = $isIndex ? self::INDEX_FILE : ltrim($path, '/');
        $candidate = $this->baseDir . '/' . $relative;

        if (!$this->isSafe($relative)) {
            $this->notFound($request, $candidate);
        }

        $file = $this->resolve($candidate);
        $contentType = $file === null ? null : $this->contentType($file);

        if ($file === null || $contentType === null) {
            $this->notFound($request, $candidate);
        }

        $body = @file_get_contents($file);

        if ($body === false) {
            $this->notFound($request, $candidate);
        }

        return $this->buildResponse($body, $contentType, $isIndex ? self::INDEX_HEADERS : self::ASSET_HEADERS);
    }

    /**
     * Tells whether the relative path has no NUL byte, no backslash, no ".." and no dotfile segment.
     *
     * @param string $relative Path relative to the base folder.
     *
     * @return boolean
     */
    private function isSafe(string $relative): bool
    {
        if (str_contains($relative, "\0") || str_contains($relative, '\\')) {
            return false;
        }

        foreach (explode('/', $relative) as $segment) {
            if (str_starts_with($segment, '.')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the real path of the candidate when it is a file under the real base folder, or null.
     *
     * @param string $candidate Base folder joined with the relative path.
     *
     * @return string|null
     */
    private function resolve(string $candidate): ?string
    {
        $real = realpath($candidate);
        $realBase = realpath($this->baseDir);

        if ($real === false || $realBase === false) {
            return null;
        }

        if (!str_starts_with($real, $realBase . DIRECTORY_SEPARATOR) || !is_file($real)) {
            return null;
        }

        return $real;
    }

    /**
     * Returns the Content-Type of the file's extension, or null when it is not on the allow-list.
     *
     * @param string $file Resolved file path.
     *
     * @return string|null
     */
    private function contentType(string $file): ?string
    {
        return self::CONTENT_TYPES[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? null;
    }

    /**
     * Builds the 200 response with the common headers, then the route-specific ones.
     *
     * @param string   $body        File contents.
     * @param string   $contentType Content-Type from the allow-list.
     * @param string[] $extra       Index or asset header lines.
     *
     * @return Response
     */
    private function buildResponse(string $body, string $contentType, array $extra): Response
    {
        return new Response($body, 200, [
            'Content-Type: ' . $contentType,
            'Content-Length: ' . strlen($body),
            'X-Content-Type-Options: nosniff',
            ...$extra,
        ]);
    }

    /**
     * Logs the candidate path, control characters escaped, and throws the router's JSON 404.
     *
     * @param ProcessingRequest $request   The request being dispatched.
     * @param string            $candidate Base folder joined with the relative path.
     *
     * @return never
     *
     * @throws NotFoundException Always.
     */
    private function notFound(ProcessingRequest $request, string $candidate): never
    {
        ($this->logger)('StaticFileHandler: not served: ' . addcslashes($candidate, "\0..\37\177"));

        throw new NotFoundException('Cannot GET ' . $request->path());
    }
}
