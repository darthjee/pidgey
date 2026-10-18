<?php

declare(strict_types=1);

namespace Pidgey\Infra\Migrations;

/**
 * Applies migrations and reports their status.
 */
interface Migrator
{
    /**
     * Applies every pending migration, in version order.
     *
     * @return MigrationResult
     *
     * @throws MigrationException When the run cannot start or a migration fails.
     */
    public function migrate(): MigrationResult;

    /**
     * Reports the status of every migration without changing anything.
     *
     * @return list<MigrationStatus>
     *
     * @throws MigrationException When the migration files are invalid.
     */
    public function status(): array;
}
