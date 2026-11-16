<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use DateTimeImmutable;
use Monolog\LogRecord;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\SmtpFailureException;
use Pidgey\Email\SendOutcome;
use Pidgey\Infra\WallClock;
use Pidgey\Model\SmtpConnection;

trait SendFlowIntegrationAssertions
{
    protected function formatted(SendOutcome $outcome, SmtpConnection $connection): string
    {
        $exception = SmtpFailureException::fromOutcome($outcome, $connection->port, $connection->encryption);

        $this->assertNoSecretIn($exception->getMessage(), $connection->username ?? self::USERNAME);

        return $this->json($exception);
    }

    protected function json(SmtpFailureException $exception): string
    {
        $clock = new class implements WallClock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-25T12:00:00.000Z');
            }
        };

        return (new ErrorFormatter($clock, $this->log->logger()))->format($exception)->body();
    }

    protected function body(
        int $status,
        string $code,
        string $message,
        ?int $replyCode = null,
        ?string $reply = null,
    ): string {
        $error = ['code' => $code, 'message' => $message];

        if ($replyCode !== null) {
            $error['smtp'] = ['code' => $replyCode, 'message' => $reply];
        }

        return (string) json_encode(
            ['error' => $error, 'statusCode' => $status, 'timestamp' => self::TIMESTAMP],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Checks the canary (plain, base64, and inside the `AUTH PLAIN` payload) is absent from the
     * outcome, the exception, the formatted body and every log record.
     */
    protected function assertNoCanary(
        SendOutcome $outcome,
        SmtpConnection $connection,
        string $username = self::USERNAME,
    ): void {
        $this->assertNoSecretIn(
            (string) json_encode([
                $outcome->failureCode(),
                $outcome->step(),
                $outcome->replyCode(),
                $outcome->replyMessage(),
                $outcome->tlsReason(),
            ], JSON_UNESCAPED_UNICODE),
            $username,
        );

        if (!$outcome->isSent()) {
            $exception = SmtpFailureException::fromOutcome($outcome, $connection->port, $connection->encryption);
            $this->assertNoSecretIn($exception->getMessage(), $username);
            $this->assertNoSecretIn($this->json($exception), $username);
        }

        $records = $this->log->records();
        $this->assertNotSame([], $records);

        foreach ($records as $record) {
            $this->assertNoSecretIn(
                (string) json_encode(
                    ['message' => $record->message, 'context' => $record->context],
                    JSON_UNESCAPED_UNICODE,
                ),
                $username,
            );
            $this->assertNoSecretIn((string) $record->formatted, $username);
        }
    }

    protected function assertNoSecretIn(string $haystack, string $username): void
    {
        $forms = [
            'plain' => self::CANARY,
            'json-escaped' => trim((string) json_encode(self::CANARY), '"'),
            'base64' => base64_encode(self::CANARY),
            'auth payload' => base64_encode("\0{$username}\0" . self::CANARY),
        ];

        foreach ($forms as $form => $secret) {
            $this->assertFalse(str_contains($haystack, $secret), "canary found ({$form})");
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function assertLogged(string $level, array $context): void
    {
        $records = array_values(array_filter(
            $this->log->records(),
            static fn (LogRecord $record): bool => $record->message === 'smtp-send-attempt',
        ));

        $this->assertCount(1, $records);
        $this->assertSame($level, strtolower($records[0]->level->getName()));
        $this->assertSame($context, $records[0]->context);
    }

    /**
     * @param resource $listener
     */
    protected function assertNotContacted($listener): void
    {
        $this->assertFalse(@stream_socket_accept($listener, 0), 'the server was contacted');
        fclose($listener);
    }
}
