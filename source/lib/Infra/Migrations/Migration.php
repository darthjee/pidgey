<?php

declare(strict_types=1);

namespace Pidgey\Infra\Migrations;

/**
 * A validated migration file holding exactly one SQL statement.
 */
final readonly class Migration
{
    /**
     * Builds a migration.
     *
     * @param integer $version  Version number parsed from the file name.
     * @param string  $name     Description part of the file name.
     * @param string  $path     Path of the migration file.
     * @param string  $sql      The single SQL statement, trimmed.
     * @param string  $checksum SHA-256 hex digest of the raw file contents.
     */
    public function __construct(
        public int $version,
        public string $name,
        public string $path,
        public string $sql,
        public string $checksum,
    ) {
    }
}
