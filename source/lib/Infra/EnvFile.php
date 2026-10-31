<?php

declare(strict_types=1);

namespace Pidgey\Infra;

/**
 * Loads an optional `KEY=VALUE` env file into the process environment.
 *
 * Variables that are already set (even to an empty string) are never overridden. The loader
 * never prints or logs keys or values.
 */
final class EnvFile
{
    private const KEY_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * Loads the env file at `$path`, adding only the variables that are unset.
     *
     * A missing file is a silent no-op.
     *
     * @param string $path Path of the env file.
     *
     * @return void
     *
     * @throws EnvFileUnreadable When the file exists but cannot be read.
     */
    public static function load(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) {
            return;
        }

        foreach (self::parse(self::read($path)) as $key => $value) {
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
            }
        }
    }

    /**
     * Parses env-file contents into key/value pairs.
     *
     * Blank lines and `#` comments are ignored; lines without `=` or with an invalid key are
     * skipped. One pair of matching surrounding quotes is stripped; there is no expansion.
     *
     * @param string $contents Env-file contents.
     *
     * @return array<string, string>
     */
    public static function parse(string $contents): array
    {
        $variables = [];

        foreach (preg_split('/\r\n|\n|\r/', $contents) as $line) {
            $trimmed = ltrim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $separator = strpos($line, '=');

            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));

            if (preg_match(self::KEY_PATTERN, $key) !== 1) {
                continue;
            }

            $variables[$key] = self::unquote(trim(substr($line, $separator + 1)));
        }

        return $variables;
    }

    /**
     * Reads the file contents.
     *
     * @param string $path Path of the env file.
     *
     * @return string
     *
     * @throws EnvFileUnreadable When the file cannot be read.
     */
    private static function read(string $path): string
    {
        $contents = is_file($path) && is_readable($path) ? @file_get_contents($path) : false;

        if ($contents === false) {
            throw new EnvFileUnreadable('env file is unreadable');
        }

        return $contents;
    }

    /**
     * Strips one pair of matching surrounding single or double quotes.
     *
     * @param string $value Trimmed value.
     *
     * @return string
     */
    private static function unquote(string $value): string
    {
        if (strlen($value) < 2) {
            return $value;
        }

        $first = $value[0];

        if (($first === '"' || $first === "'") && $value[-1] === $first) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
