<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Smtp;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Guard: TLS verification is never disabled, in the app or in its tests.
 */
#[CoversNothing]
final class NoDisabledVerificationTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /** Patterns that disable or weaken certificate verification. */
    private const FORBIDDEN = [
        '/[\'"]verify_peer[\'"]\s*=>\s*(false|0)\b/i',
        '/[\'"]verify_peer_name[\'"]\s*=>\s*(false|0)\b/i',
        '/allow_self_signed/i',
    ];

    public function testNoFileDisablesVerification(): void
    {
        $scanned = 0;

        foreach (['lib', 'tests'] as $directory) {
            foreach ($this->phpFiles(self::ROOT . '/' . $directory) as $path) {
                if (realpath($path) === realpath(__FILE__)) {
                    continue;
                }

                $scanned++;
                $code = (string) file_get_contents($path);

                foreach (self::FORBIDDEN as $pattern) {
                    $this->assertDoesNotMatchRegularExpression(
                        $pattern,
                        $code,
                        sprintf('%s disables TLS verification', $this->relative($path)),
                    );
                }
            }
        }

        $this->assertGreaterThan(0, $scanned);
    }

    private function relative(string $path): string
    {
        return substr((string) realpath($path), strlen((string) realpath(self::ROOT)) + 1);
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
