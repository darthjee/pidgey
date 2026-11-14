<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\OperatorLog;
use Pidgey\Infra\LoggerFactory;
use Pidgey\Tests\Support\CapturedLog;

#[CoversClass(OperatorLog::class)]
#[UsesClass(LoggerFactory::class)]
final class OperatorLogTest extends TestCase
{
    public function testWritesOneInfoLineWithoutFields(): void
    {
        $log = new CapturedLog();

        (new OperatorLog($log->logger()))->record(OperatorLog::SMTP_CONNECTION_ACTIVATE, 3, 5, 7);

        $records = $log->records();
        $this->assertCount(1, $records);
        $this->assertSame(Level::Info, $records[0]->level);
        $this->assertSame('smtp-connection-activate', $records[0]->message);
        $this->assertSame(['userId' => 3, 'organizationId' => 5, 'connectionId' => 7], $records[0]->context);
    }

    public function testUpdateLineCarriesTheFieldNames(): void
    {
        $log = new CapturedLog();

        (new OperatorLog($log->logger()))->record(
            OperatorLog::SMTP_CONNECTION_UPDATE,
            3,
            5,
            7,
            ['host', 'password'],
        );

        $records = $log->records();
        $this->assertCount(1, $records);
        $this->assertSame('smtp-connection-update', $records[0]->message);
        $this->assertSame(
            ['userId' => 3, 'organizationId' => 5, 'connectionId' => 7, 'fields' => ['host', 'password']],
            $records[0]->context,
        );
        $this->assertStringContainsString('"fields":["host","password"]', $log->lines()[0]);
    }

    public function testTestLineCarriesTheOutcome(): void
    {
        $log = new CapturedLog();

        (new OperatorLog($log->logger()))->record(OperatorLog::SMTP_CONNECTION_TEST, 3, 5, 7, null, 'sent');

        $records = $log->records();
        $this->assertSame('smtp-connection-test', $records[0]->message);
        $this->assertSame(
            ['userId' => 3, 'organizationId' => 5, 'connectionId' => 7, 'outcome' => 'sent'],
            $records[0]->context,
        );
    }

    public function testActionNames(): void
    {
        $this->assertSame('smtp-connection-create', OperatorLog::SMTP_CONNECTION_CREATE);
        $this->assertSame('smtp-connection-update', OperatorLog::SMTP_CONNECTION_UPDATE);
        $this->assertSame('smtp-connection-delete', OperatorLog::SMTP_CONNECTION_DELETE);
        $this->assertSame('smtp-connection-activate', OperatorLog::SMTP_CONNECTION_ACTIVATE);
        $this->assertSame('smtp-connection-deactivate', OperatorLog::SMTP_CONNECTION_DEACTIVATE);
        $this->assertSame('smtp-connection-test', OperatorLog::SMTP_CONNECTION_TEST);
    }
}
