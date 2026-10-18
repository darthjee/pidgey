<?php

declare(strict_types=1);

namespace Pidgey\Infra;

use RuntimeException;

/**
 * Reads configuration from environment variables.
 *
 * Unset and empty variables are treated the same way.
 */
final class Env
{
    /**
     * Returns the value of an environment variable, or a default when it is unset or empty.
     *
     * @param string      $name    Name of the environment variable.
     * @param string|null $default Value returned when the variable is unset or empty.
     *
     * @return string|null
     */
    public static function get(string $name, ?string $default = null): ?string
    {
        $value = getenv($name);

        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }

    /**
     * Returns the value of an environment variable that must be set.
     *
     * @param string $name Name of the environment variable.
     *
     * @return string
     *
     * @throws RuntimeException When the variable is unset or empty.
     */
    public static function required(string $name): string
    {
        $value = self::get($name);

        if ($value === null) {
            throw new RuntimeException("Missing required environment variable: {$name}");
        }

        return $value;
    }

    /**
     * Returns an environment variable as a non-negative integer, or a default when it is unset or empty.
     *
     * @param string  $name    Name of the environment variable.
     * @param integer $default Value returned when the variable is unset or empty.
     *
     * @return integer
     *
     * @throws RuntimeException When the value is not a non-negative integer.
     */
    public static function integer(string $name, int $default): int
    {
        $value = self::get($name);

        if ($value === null) {
            return $default;
        }

        if (preg_match('/^[0-9]+$/', $value) !== 1) {
            throw new RuntimeException("Invalid {$name}: {$value} (expected a non-negative integer)");
        }

        return (int) $value;
    }
}
