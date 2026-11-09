<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Support;

use Monolog\Level;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Tests\Support\CapturedLog;

#[CoversNothing]
final class CapturedLogTest extends TestCase
{
    private const SECRET = 'distinctive-secret-password-7f3a';

    public function testDebugRecordsAreCaptured(): void
    {
        $log = new CapturedLog();

        $log->logger()->debug('connecting', ['host' => 'smtp.example']);

        $this->assertCount(1, $log->records());
        $this->assertSame(Level::Debug, $log->records()[0]->level);
        $this->assertCount(1, $log->lines());
        $this->assertStringStartsWith('pidgey.DEBUG: connecting', $log->lines()[0]);
    }

    public function testAssertNotLoggedPassesWhenTheSecretIsAbsent(): void
    {
        $log = new CapturedLog();
        $log->logger()->info('nothing to hide', ['host' => 'smtp.example']);

        $log->assertNotLogged(self::SECRET);

        $this->assertCount(1, $log->lines());
    }

    /**
     * @return array<string, array{string, array<string, string>, string}>
     */
    public static function leaks(): array
    {
        return [
            'plain in the message' => ['auth with ' . self::SECRET, [], 'plain'],
            'plain in the context' => ['auth', ['password' => self::SECRET], 'plain'],
            'base64 in the message' => ['AUTH PLAIN ' . base64_encode(self::SECRET), [], 'base64'],
        ];
    }

    /**
     * @param array<string, string> $context
     */
    #[DataProvider('leaks')]
    public function testAssertNotLoggedFailsWithoutRevealingTheSecret(
        string $message,
        array $context,
        string $form,
    ): void {
        $log = new CapturedLog();
        $log->logger()->info('first line');
        $log->logger()->warning($message, $context);

        try {
            $log->assertNotLogged(self::SECRET);
        } catch (AssertionFailedError $failure) {
            $this->assertSame("secret found in log line 2 ({$form})", $failure->getMessage());
            $this->assertStringNotContainsString(self::SECRET, $failure->getMessage());
            $this->assertStringNotContainsString(base64_encode(self::SECRET), $failure->getMessage());

            return;
        }

        $this->fail('assertNotLogged() did not fail.');
    }
}
