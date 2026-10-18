<?php

declare(strict_types=1);

namespace Pidgey\Infra\Migrations;

/**
 * Status of one migration version, comparing the applied state with the files.
 */
final readonly class MigrationStatus
{
    /**
     * Builds a status entry.
     *
     * @param integer        $version Version number.
     * @param string         $name    Migration name (from the file, or from the applied row when the file is missing).
     * @param MigrationState $state   State of the migration.
     * @param string|null    $path    Path of the migration file, null when the file is missing.
     */
    public function __construct(
        public int $version,
        public string $name,
        public MigrationState $state,
        public ?string $path,
    ) {
    }
}
