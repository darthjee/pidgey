<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra\Migrations;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\Migrations\Migration;
use Pidgey\Infra\Migrations\MigrationException;
use Pidgey\Infra\Migrations\MigrationPlanner;
use Pidgey\Infra\Migrations\MigrationStatus;

#[CoversClass(MigrationPlanner::class)]
#[CoversClass(MigrationStatus::class)]
#[CoversClass(Migration::class)]
final class MigrationPlannerTest extends TestCase
{
    public function testNothingPending(): void
    {
        $migrations = [$this->migration(1), $this->migration(2)];
        $applied = [1 => $this->row(1), 2 => $this->row(2)];
        $planner = new MigrationPlanner();

        $this->assertSame([], $planner->pending($applied, $migrations));
        $this->assertSame(
            [[1, 'applied'], [2, 'applied']],
            $this->summary($planner->statuses($applied, $migrations)),
        );
    }

    public function testNothingAppliedEverythingPending(): void
    {
        $migrations = [$this->migration(1), $this->migration(2)];
        $planner = new MigrationPlanner();

        $this->assertSame($migrations, $planner->pending([], $migrations));
        $this->assertSame(
            [[1, 'pending'], [2, 'pending']],
            $this->summary($planner->statuses([], $migrations)),
        );
    }

    public function testNoFilesNoRows(): void
    {
        $planner = new MigrationPlanner();

        $this->assertSame([], $planner->pending([], []));
        $this->assertSame([], $planner->statuses([], []));
    }

    public function testPendingAfterApplied(): void
    {
        $migrations = [$this->migration(1), $this->migration(2), $this->migration(3)];
        $applied = [1 => $this->row(1)];

        $this->assertSame(
            [$migrations[1], $migrations[2]],
            (new MigrationPlanner())->pending($applied, $migrations),
        );
    }

    public function testGapsAllowed(): void
    {
        $migrations = [$this->migration(1), $this->migration(3), $this->migration(7)];
        $applied = [1 => $this->row(1), 3 => $this->row(3)];
        $planner = new MigrationPlanner();

        $this->assertSame([$migrations[2]], $planner->pending($applied, $migrations));
        $this->assertSame(
            [[1, 'applied'], [3, 'applied'], [7, 'pending']],
            $this->summary($planner->statuses($applied, $migrations)),
        );
    }

    public function testMissingFile(): void
    {
        $migrations = [$this->migration(2)];
        $applied = [1 => $this->row(1), 2 => $this->row(2)];
        $planner = new MigrationPlanner();

        $statuses = $planner->statuses($applied, $migrations);
        $this->assertSame([[1, 'missing_file'], [2, 'applied']], $this->summary($statuses));
        $this->assertSame('step_1', $statuses[0]->name);
        $this->assertNull($statuses[0]->path);

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Applied migration 0001_step_1 has no migration file');

        $planner->pending($applied, $migrations);
    }

    public function testChecksumMismatch(): void
    {
        $migrations = [$this->migration(1), $this->migration(2)];
        $applied = [1 => ['name' => 'step_1', 'checksum' => str_repeat('0', 64)]];
        $planner = new MigrationPlanner();

        $this->assertSame(
            [[1, 'checksum_mismatch'], [2, 'pending']],
            $this->summary($planner->statuses($applied, $migrations)),
        );

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Applied migration file was modified (checksum mismatch): /m/0001_step_1.sql');

        $planner->pending($applied, $migrations);
    }

    public function testOutOfOrder(): void
    {
        $migrations = [$this->migration(4), $this->migration(5), $this->migration(6)];
        $applied = [5 => $this->row(5)];
        $planner = new MigrationPlanner();

        $this->assertSame(
            [[4, 'out_of_order'], [5, 'applied'], [6, 'pending']],
            $this->summary($planner->statuses($applied, $migrations)),
        );

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage(
            'Pending migration is older than the latest applied version: /m/0004_step_4.sql',
        );

        $planner->pending($applied, $migrations);
    }

    public function testReportsEveryProblem(): void
    {
        $migrations = [$this->migration(2), $this->migration(3)];
        $applied = [
            1 => $this->row(1),
            3 => ['name' => 'step_3', 'checksum' => 'bad'],
            4 => $this->row(4),
        ];

        try {
            (new MigrationPlanner())->pending($applied, $migrations);
            $this->fail('Expected a MigrationException');
        } catch (MigrationException $exception) {
            $this->assertSame(
                "Applied migration 0001_step_1 has no migration file\n"
                . "Pending migration is older than the latest applied version: /m/0002_step_2.sql\n"
                . "Applied migration file was modified (checksum mismatch): /m/0003_step_3.sql\n"
                . 'Applied migration 0004_step_4 has no migration file',
                $exception->getMessage(),
            );
        }
    }

    private function migration(int $version): Migration
    {
        return new Migration(
            $version,
            "step_{$version}",
            sprintf('/m/%04d_step_%d.sql', $version, $version),
            "SELECT {$version}",
            $this->checksum($version),
        );
    }

    /**
     * @return array{name: string, checksum: string}
     */
    private function row(int $version): array
    {
        return ['name' => "step_{$version}", 'checksum' => $this->checksum($version)];
    }

    private function checksum(int $version): string
    {
        return hash('sha256', "SELECT {$version}");
    }

    /**
     * @param list<MigrationStatus> $statuses
     *
     * @return list<array{int, string}>
     */
    private function summary(array $statuses): array
    {
        return array_map(
            static fn (MigrationStatus $status): array => [$status->version, $status->state->value],
            $statuses,
        );
    }
}
