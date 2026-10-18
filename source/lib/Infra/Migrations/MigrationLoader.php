<?php

declare(strict_types=1);

namespace Pidgey\Infra\Migrations;

/**
 * Reads and validates the migration files of a directory.
 *
 * Only `.sql` files are considered; each one must be named `NNNN_description.sql`, use a
 * unique version and hold exactly one SQL statement.
 */
final class MigrationLoader
{
    private const FILE_PATTERN = '/^(\d{4})_([a-z0-9_]+)\.sql$/';

    /**
     * Builds the loader.
     *
     * @param StatementSplitter $splitter Splits file contents into statements.
     */
    public function __construct(private readonly StatementSplitter $splitter = new StatementSplitter())
    {
    }

    /**
     * Loads the migrations of a directory, sorted by version.
     *
     * @param string $directory Migrations directory.
     *
     * @return list<Migration>
     *
     * @throws MigrationException When the directory is missing or any file is invalid.
     */
    public function load(string $directory): array
    {
        $byVersion = $this->groupByVersion($directory, $this->sqlFiles($directory));
        $this->assertUniqueVersions($byVersion);
        ksort($byVersion);

        $migrations = [];

        foreach ($byVersion as $version => $files) {
            $migrations[] = $this->build($version, $files[0]['name'], $files[0]['path']);
        }

        return $migrations;
    }

    /**
     * Lists the `.sql` file names of the directory, sorted.
     *
     * @param string $directory Migrations directory.
     *
     * @return list<string>
     *
     * @throws MigrationException When the directory is missing or unreadable.
     */
    private function sqlFiles(string $directory): array
    {
        $entries = is_dir($directory) ? scandir($directory) : false;

        if ($entries === false) {
            throw new MigrationException("Migrations directory not found: {$directory}");
        }

        $files = array_filter(
            $entries,
            static fn (string $entry): bool => str_ends_with($entry, '.sql')
                && is_file($directory . DIRECTORY_SEPARATOR . $entry),
        );

        return array_values($files);
    }

    /**
     * Parses the file names and groups the files by version.
     *
     * @param string   $directory Migrations directory.
     * @param string[] $files     File names.
     *
     * @return array<int, list<array{name: string, path: string}>>
     *
     * @throws MigrationException When a file name does not match `NNNN_description.sql`.
     */
    private function groupByVersion(string $directory, array $files): array
    {
        $byVersion = [];

        foreach ($files as $file) {
            $path = $directory . DIRECTORY_SEPARATOR . $file;

            if (preg_match(self::FILE_PATTERN, $file, $matches) !== 1) {
                throw new MigrationException(
                    "Invalid migration file name (expected NNNN_description.sql): {$path}",
                );
            }

            $byVersion[(int) $matches[1]][] = ['name' => $matches[2], 'path' => $path];
        }

        return $byVersion;
    }

    /**
     * Ensures no two files share a version.
     *
     * @param array<int, list<array{name: string, path: string}>> $byVersion Files grouped by version.
     *
     * @return void
     *
     * @throws MigrationException Listing every conflicting file.
     */
    private function assertUniqueVersions(array $byVersion): void
    {
        $conflicts = [];

        foreach ($byVersion as $files) {
            if (count($files) > 1) {
                array_push($conflicts, ...array_column($files, 'path'));
            }
        }

        if ($conflicts !== []) {
            throw new MigrationException('Duplicate migration versions: ' . implode(', ', $conflicts));
        }
    }

    /**
     * Reads a migration file and checks it holds exactly one statement.
     *
     * @param integer $version Version number.
     * @param string  $name    Description part of the file name.
     * @param string  $path    Path of the file.
     *
     * @return Migration
     *
     * @throws MigrationException When the file cannot be read or does not hold exactly one statement.
     */
    private function build(int $version, string $name, string $path): Migration
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new MigrationException("Unable to read migration file: {$path}");
        }

        $statements = $this->splitter->split($contents);

        if ($statements === []) {
            throw new MigrationException("Migration file has no SQL statement: {$path}");
        }

        if (count($statements) > 1) {
            throw new MigrationException(
                'Migration file must contain exactly one statement, found ' . count($statements) . ": {$path}",
            );
        }

        return new Migration($version, $name, $path, $statements[0], hash('sha256', $contents));
    }
}
