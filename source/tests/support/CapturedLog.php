<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use PHPUnit\Framework\Assert;
use Pidgey\Infra\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * Captures what the application logs, with the production channel and line format.
 *
 * Inject it with `new Container(logger: $log->logger())`, or return such a container from the
 * container-factory closures of `FrontController` and `SessionEndpointTestCase`. Then read
 * `records()` or `lines()`, and check with `assertNotLogged()` that no secret leaked.
 */
final class CapturedLog
{
    private readonly TestHandler $handler;

    private readonly LoggerInterface $logger;

    public function __construct()
    {
        $this->handler = new TestHandler();
        $this->logger = LoggerFactory::create('debug', $this->handler);
    }

    public function logger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @return list<LogRecord>
     */
    public function records(): array
    {
        return array_values($this->handler->getRecords());
    }

    /**
     * The formatted lines, as the production handler would write them.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        return array_map(static fn (LogRecord $record): string => (string) $record->formatted, $this->records());
    }

    /**
     * Fails when any line contains the secret, plain or base64-encoded; the failure message never
     * contains the secret.
     */
    public function assertNotLogged(string $secret): void
    {
        $forms = ['plain' => $secret, 'base64' => base64_encode($secret)];

        foreach ($this->lines() as $index => $line) {
            foreach ($forms as $form => $value) {
                if (str_contains($line, $value)) {
                    Assert::fail(sprintf('secret found in log line %d (%s)', $index + 1, $form));
                }
            }
        }

        Assert::assertTrue(true);
    }
}
