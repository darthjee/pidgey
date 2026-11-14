<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Email;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Email\TestEmailLease;
use Pidgey\Email\TestEmailLimit;
use Pidgey\Http\HttpException;
use Pidgey\Http\TooManyRequestsException;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\LoggerFactory;
use Pidgey\Infra\PdoFactory;
use Pidgey\Infra\Transaction;
use Pidgey\Model\Row;
use Pidgey\Model\SmtpTestLimit;
use Pidgey\Repository\Sql;
use Pidgey\Repository\SmtpTestLimitRepository;
use Pidgey\Tests\Support\CapturedLog;
use Pidgey\Tests\Support\DatabaseCleaner;
use Pidgey\Tests\Support\Fixtures;
use Pidgey\Tests\Support\FrozenWallClock;
use Pidgey\Tests\Support\IntegrationTestCase;

#[CoversClass(TestEmailLimit::class)]
#[CoversClass(TestEmailLease::class)]
#[CoversClass(TooManyRequestsException::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(SmtpTestLimit::class)]
#[UsesClass(SmtpTestLimitRepository::class)]
#[UsesClass(Row::class)]
#[UsesClass(Sql::class)]
#[UsesClass(Transaction::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(LoggerFactory::class)]
final class TestEmailLimitTest extends IntegrationTestCase
{
    private const START = 1_800_000_000;

    private const MAX = 2;

    private const WINDOW = 100;

    private const LEASE = 30;

    private PDO $pdo;

    private int $organizationId;

    private FrozenWallClock $clock;

    private CapturedLog $log;

    private SmtpTestLimitRepository $repository;

    private TestEmailLimit $limit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = self::pdo();
        DatabaseCleaner::truncateAll($this->pdo);
        $this->organizationId = (new Fixtures($this->pdo))->organization();
        $this->clock = new FrozenWallClock(self::START);
        $this->log = new CapturedLog();
        $this->repository = new SmtpTestLimitRepository($this->pdo);
        $this->limit = $this->build($this->repository);
    }

    protected function tearDown(): void
    {
        DatabaseCleaner::truncateAll($this->pdo);

        parent::tearDown();
    }

    public function testTheFirstAttemptTakesTheLeaseAndCountsOne(): void
    {
        $lease = $this->limit->acquire($this->organizationId, 9);

        $this->assertSame($this->organizationId, $lease->organizationId);
        $this->assertSame(9, $lease->connectionId);
        $this->assertSame(self::START + self::LEASE, $lease->busyUntil->getTimestamp());
        $this->assertSame([1, self::START + self::LEASE], $this->state());
    }

    public function testReleaseFreesTheLeaseButKeepsTheCount(): void
    {
        $lease = $this->limit->acquire($this->organizationId, 9);

        $this->limit->release($lease);

        $this->assertSame([1, null], $this->state());
    }

    public function testAHeldLeaseRefusesWithItsRemainingTime(): void
    {
        $this->limit->acquire($this->organizationId, 9);
        $this->clock->advance(10);

        $error = $this->refusal();

        $this->assertSame('A test email is already in progress', $error->errorMessage());
        $this->assertSame(20, $error->retryAfter());
        $this->assertSame(['Retry-After: 20'], $error->headers());
        $this->assertSame([1, self::START + self::LEASE], $this->state());
    }

    public function testTheLeaseWinsOverAFullWindow(): void
    {
        $now = $this->clock->now();
        $this->repository->lockForOrganization($this->organizationId, $now);
        $leaseEnd = $now->modify('+' . self::LEASE . ' seconds');
        $this->repository->takeAttempt($this->organizationId, $now, self::MAX, $leaseEnd);

        $this->assertSame(
            'A test email is already in progress',
            $this->refusal()->errorMessage(),
        );
    }

    public function testAFullWindowRefusesWithTheRestOfTheWindow(): void
    {
        $this->fill();
        $this->clock->advance(40);

        $error = $this->refusal();

        $this->assertSame('Too many test emails; try again later', $error->errorMessage());
        $this->assertSame(self::WINDOW - 40, $error->retryAfter());
        $this->assertSame([self::MAX, null], $this->state());
    }

    public function testAtTheWindowBoundaryANewWindowStarts(): void
    {
        $this->fill();
        $this->clock->advance(self::WINDOW);

        $lease = $this->limit->acquire($this->organizationId, 9);

        $this->assertSame([1, self::START + self::WINDOW + self::LEASE], $this->state());
        $this->assertSame(self::START + self::WINDOW, $this->windowStart());
        $this->limit->release($lease);
    }

    public function testJustBeforeTheBoundaryTheWindowIsStillFull(): void
    {
        $this->fill();
        $this->clock->advance(self::WINDOW - 1);

        $this->assertSame(1, $this->refusal()->retryAfter());
    }

    public function testAnExpiredLeaseOfADeadWorkerIsIgnored(): void
    {
        $this->limit->acquire($this->organizationId, 9);
        $this->clock->advance(self::LEASE);

        $lease = $this->limit->acquire($this->organizationId, 9);

        $this->assertSame([2, self::START + 2 * self::LEASE], $this->state());
        $this->limit->release($lease);
    }

    public function testARefusedAttemptDoesNotCount(): void
    {
        $this->limit->acquire($this->organizationId, 9);
        $this->refusal();
        $this->refusal();

        $this->assertSame(1, $this->state()[0]);
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function roundings(): array
    {
        return ['a fraction of a second' => [200_000, 1], 'above a second' => [1_200_000, 2]];
    }

    #[DataProvider('roundings')]
    public function testRetryAfterIsRoundedUpToAtLeastOneSecond(int $microseconds, int $expected): void
    {
        $this->limit->acquire($this->organizationId, 9);
        $remaining = self::LEASE * 1_000_000 - $microseconds;
        $this->clock = new FrozenWallClock(
            (new DateTimeImmutable('@' . self::START))
                ->modify('+' . intdiv($remaining, 1_000_000) . ' seconds')
                ->modify('+' . ($remaining % 1_000_000) . ' microseconds'),
        );
        $this->limit = $this->build($this->repository);

        $this->assertSame($expected, $this->refusal()->retryAfter());
    }

    public function testAFailingReleaseReturnsNormallyAndLogsAWarning(): void
    {
        $lease = $this->limit->acquire($this->organizationId, 9);
        $broken = new class extends PDO {
            public function __construct()
            {
            }

            public function prepare(string $query, array $options = []): never
            {
                unset($query, $options);

                throw new PDOException('boom');
            }
        };
        $limit = $this->build(new SmtpTestLimitRepository($broken));

        $limit->release($lease);

        $lines = $this->log->lines();
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('"organizationId":' . $this->organizationId, $lines[0]);
        $this->assertStringContainsString('"connectionId":9', $lines[0]);
        $this->assertStringContainsString(PDOException::class, $lines[0]);
    }

    private function build(SmtpTestLimitRepository $repository): TestEmailLimit
    {
        return new TestEmailLimit(
            $repository,
            new Transaction($this->pdo),
            $this->clock,
            $this->log->logger(),
            self::MAX,
            self::WINDOW,
            self::LEASE,
        );
    }

    private function fill(): void
    {
        for ($i = 0; $i < self::MAX; $i++) {
            $this->limit->release($this->limit->acquire($this->organizationId, 9));
        }
    }

    private function refusal(): TooManyRequestsException
    {
        try {
            $this->limit->acquire($this->organizationId, 9);
        } catch (TooManyRequestsException $error) {
            return $error;
        }

        $this->fail('Expected a TooManyRequestsException.');
    }

    /**
     * @return array{int, int|null}
     */
    private function state(): array
    {
        $row = $this->pdo->query(
            'SELECT attempts, busy_until FROM smtp_test_limits',
        )->fetch(PDO::FETCH_ASSOC);

        return [(int) $row['attempts'], $row['busy_until'] === null ? null : self::timestamp($row['busy_until'])];
    }

    private function windowStart(): int
    {
        $value = $this->pdo->query('SELECT window_started_at FROM smtp_test_limits')->fetchColumn();

        return self::timestamp((string) $value);
    }

    private static function timestamp(string $dateTime): int
    {
        return (new DateTimeImmutable($dateTime, new DateTimeZone('UTC')))->getTimestamp();
    }
}
