<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Model\SmtpConnection;
use Pidgey\Model\SmtpEncryption;

abstract class SmtpConnectionRulesTestCase extends TestCase
{
    protected const CANARY = 'canary-pw-9b1e';

    protected const STORED_BYTES = 'stored-encrypted-bytes';

    protected const HOST_MESSAGE = 'host must be a hostname or an IP address';

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    protected function createBody(array $override = []): array
    {
        return $override + [
            'name' => 'Office relay',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'fromAddress' => 'noreply@example.com',
        ];
    }

    protected function stored(
        ?string $username = 'relay-user',
        ?string $password = self::STORED_BYTES,
        SmtpEncryption $encryption = SmtpEncryption::Tls,
        bool $active = true,
    ): SmtpConnection {
        $utc = new DateTimeZone('UTC');

        return new SmtpConnection(
            12,
            34,
            'Office relay',
            'smtp.example.com',
            587,
            $encryption,
            $username,
            $password,
            'noreply@example.com',
            $active,
            new DateTimeImmutable('2026-10-17 12:00:00', $utc),
            new DateTimeImmutable('2026-10-20 09:30:00', $utc),
        );
    }

    /**
     * @param callable(): mixed $call
     * @param list<string> $messages
     */
    protected function assertFailure(callable $call, array $messages): void
    {
        $this->assertSame($messages, $this->failure($call));
    }

    /**
     * The validation details of the call, or an empty list when it passes.
     *
     * @param callable(): mixed $call
     *
     * @return list<string>
     */
    protected function failure(callable $call): array
    {
        try {
            $call();
        } catch (ValidationFailedException $error) {
            return $error->details() ?? [];
        }

        return [];
    }

    protected static function longHost(int $length): string
    {
        $labels = [];
        $remaining = $length;

        while ($remaining > 64) {
            $labels[] = str_repeat('a', 63);
            $remaining -= 64;
        }

        $labels[] = str_repeat('b', $remaining);

        return implode('.', $labels);
    }
}
