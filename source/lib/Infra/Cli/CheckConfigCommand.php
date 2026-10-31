<?php

declare(strict_types=1);

namespace Pidgey\Infra\Cli;

use Closure;
use Pidgey\Infra\ConfigValidator;
use Pidgey\Infra\EnvFile;
use Pidgey\Infra\EnvFileUnreadable;

/**
 * The `bin/check-config` command: checks the configuration without starting the app.
 *
 * Loads the env file, runs the boot validation and checks the PHP version. Exits 0 with
 * `configuration OK` on stdout, or 1 with one `<CHECK>: <reason>` line per failure on stderr.
 * It never prints a value and never connects to the database.
 */
final class CheckConfigCommand
{
    public const EXIT_SUCCESS = 0;
    public const EXIT_FAILURE = 1;

    private const REQUIRED_PHP_SERIES = '8.4.';

    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    /**
     * Builds the command.
     *
     * @param string   $envFile    Path of the env file to load.
     * @param Closure  $validator  Closure(): list<string>, returns the configuration failures.
     * @param resource $stdout     Stream for normal output.
     * @param resource $stderr     Stream for error output.
     * @param string   $phpVersion Version of the running PHP.
     */
    public function __construct(
        private readonly string $envFile,
        private readonly Closure $validator,
        $stdout,
        $stderr,
        private readonly string $phpVersion = PHP_VERSION,
    ) {
        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }

    /**
     * Builds the command with the real validator and the running PHP version.
     *
     * @param string   $envFile Path of the env file to load.
     * @param resource $stdout  Stream for normal output.
     * @param resource $stderr  Stream for error output.
     *
     * @return self
     */
    public static function create(string $envFile, $stdout, $stderr): self
    {
        $validator = new ConfigValidator();

        return new self($envFile, static fn (): array => $validator->validate(), $stdout, $stderr);
    }

    /**
     * Runs every check and reports the outcome.
     *
     * @return integer Exit code.
     */
    public function run(): int
    {
        $failures = [];

        try {
            EnvFile::load($this->envFile);
        } catch (EnvFileUnreadable) {
            $failures[] = 'env file: unreadable';
        }

        $failures = [...$failures, ...($this->validator)()];

        if (!str_starts_with($this->phpVersion, self::REQUIRED_PHP_SERIES)) {
            $failures[] = 'PHP version: not 8.4.x';
        }

        if ($failures === []) {
            fwrite($this->stdout, "configuration OK\n");

            return self::EXIT_SUCCESS;
        }

        foreach ($failures as $failure) {
            fwrite($this->stderr, $failure . "\n");
        }

        return self::EXIT_FAILURE;
    }
}
