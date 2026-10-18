<?php

declare(strict_types=1);

namespace Pidgey\Infra\Migrations;

/**
 * Compares the applied migrations with the migration files.
 *
 * Pure logic shared by the runner and the status report.
 */
final class MigrationPlanner
{
    /**
     * Returns the status of every version known from the files or the applied rows.
     *
     * @param array<int, array{name: string, checksum: string}> $applied    Applied rows, keyed by version.
     * @param Migration[]                                       $migrations Migration files, sorted by version.
     *
     * @return list<MigrationStatus> Sorted by version.
     */
    public function statuses(array $applied, array $migrations): array
    {
        $highestApplied = $applied === [] ? 0 : max(array_keys($applied));
        $statuses = [];

        foreach ($migrations as $migration) {
            $statuses[$migration->version] = new MigrationStatus(
                $migration->version,
                $migration->name,
                $this->fileState($migration, $applied[$migration->version] ?? null, $highestApplied),
                $migration->path,
            );
        }

        foreach ($applied as $version => $row) {
            if (!isset($statuses[$version])) {
                $statuses[$version] = new MigrationStatus($version, $row['name'], MigrationState::MissingFile, null);
            }
        }

        ksort($statuses);

        return array_values($statuses);
    }

    /**
     * Returns the migrations to apply, after checking the files against the applied rows.
     *
     * @param array<int, array{name: string, checksum: string}> $applied    Applied rows, keyed by version.
     * @param Migration[]                                       $migrations Migration files, sorted by version.
     *
     * @return list<Migration> Pending migrations, sorted by version.
     *
     * @throws MigrationException When an applied file is missing or edited, or a pending file is out of order.
     */
    public function pending(array $applied, array $migrations): array
    {
        $problems = [];
        $pendingVersions = [];

        foreach ($this->statuses($applied, $migrations) as $status) {
            if ($status->state === MigrationState::Pending) {
                $pendingVersions[$status->version] = true;
            } elseif ($status->state !== MigrationState::Applied) {
                $problems[] = $this->describe($status);
            }
        }

        if ($problems !== []) {
            throw new MigrationException(implode("\n", $problems));
        }

        return array_values(array_filter(
            $migrations,
            static fn (Migration $migration): bool => isset($pendingVersions[$migration->version]),
        ));
    }

    /**
     * Computes the state of a migration file.
     *
     * @param Migration                                  $migration      Migration file.
     * @param array{name: string, checksum: string}|null $row            Applied row for its version, if any.
     * @param integer                                    $highestApplied Highest applied version (0 when none).
     *
     * @return MigrationState
     */
    private function fileState(Migration $migration, ?array $row, int $highestApplied): MigrationState
    {
        if ($row !== null) {
            return hash_equals($row['checksum'], $migration->checksum)
                ? MigrationState::Applied
                : MigrationState::ChecksumMismatch;
        }

        return $migration->version < $highestApplied ? MigrationState::OutOfOrder : MigrationState::Pending;
    }

    /**
     * Describes a problematic status for an error message.
     *
     * @param MigrationStatus $status Status that blocks the run.
     *
     * @return string
     */
    private function describe(MigrationStatus $status): string
    {
        $label = sprintf('%04d_%s', $status->version, $status->name);

        return match ($status->state) {
            MigrationState::MissingFile => "Applied migration {$label} has no migration file",
            MigrationState::ChecksumMismatch
                => "Applied migration file was modified (checksum mismatch): {$status->path}",
            default => "Pending migration is older than the latest applied version: {$status->path}",
        };
    }
}
