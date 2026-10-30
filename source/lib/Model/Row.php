<?php

declare(strict_types=1);

namespace Pidgey\Model;

use BackedEnum;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use LogicException;
use UnexpectedValueException;

/**
 * Reads typed values from a `FETCH_ASSOC` row, keyed by column name.
 *
 * Every entity's `fromRow()` converts its columns through these helpers, so conversions are
 * the same everywhere. A missing column throws `LogicException` (a programmer error); a value
 * of the wrong shape throws `UnexpectedValueException`. Messages name the calling class and
 * the column, **never the value**: a row may hold password digests, token hashes or SMTP
 * ciphertext.
 */
final class Row
{
    private const DATE_TIME_FORMATS = ['!Y-m-d H:i:s', '!Y-m-d H:i:s.u'];

    /**
     * Reads an integer column.
     *
     * @param array<string, mixed> $row    Row keyed by column name.
     * @param string               $column Column name.
     *
     * @return integer
     *
     * @throws LogicException           When the column is missing.
     * @throws UnexpectedValueException When the value is not an integer.
     */
    public static function int(array $row, string $column): int
    {
        return self::toInt(self::required($row, $column), $column);
    }

    /**
     * Reads a nullable integer column.
     *
     * @param array<string, mixed> $row    Row keyed by column name.
     * @param string               $column Column name.
     *
     * @return integer|null
     *
     * @throws LogicException           When the column is missing.
     * @throws UnexpectedValueException When the value is neither null nor an integer.
     */
    public static function nullableInt(array $row, string $column): ?int
    {
        $value = self::value($row, $column);

        return $value === null ? null : self::toInt($value, $column);
    }

    /**
     * Reads a string column (`VARCHAR`, `CHAR`, `VARBINARY`), as is.
     *
     * @param array<string, mixed> $row    Row keyed by column name.
     * @param string               $column Column name.
     *
     * @return string
     *
     * @throws LogicException           When the column is missing.
     * @throws UnexpectedValueException When the value is not a string.
     */
    public static function string(array $row, string $column): string
    {
        return self::toString(self::required($row, $column), $column);
    }

    /**
     * Reads a nullable string column, as is.
     *
     * @param array<string, mixed> $row    Row keyed by column name.
     * @param string               $column Column name.
     *
     * @return string|null
     *
     * @throws LogicException           When the column is missing.
     * @throws UnexpectedValueException When the value is neither null nor a string.
     */
    public static function nullableString(array $row, string $column): ?string
    {
        $value = self::value($row, $column);

        return $value === null ? null : self::toString($value, $column);
    }

    /**
     * Reads a `BOOLEAN` (`TINYINT(1)`) column: `0`/`1` become `false`/`true`.
     *
     * @param array<string, mixed> $row    Row keyed by column name.
     * @param string               $column Column name.
     *
     * @return boolean
     *
     * @throws LogicException           When the column is missing.
     * @throws UnexpectedValueException When the value is not 0 or 1.
     */
    public static function bool(array $row, string $column): bool
    {
        return self::toBool(self::required($row, $column), $column);
    }

    /**
     * Reads a nullable `BOOLEAN` column.
     *
     * @param array<string, mixed> $row    Row keyed by column name.
     * @param string               $column Column name.
     *
     * @return boolean|null
     *
     * @throws LogicException           When the column is missing.
     * @throws UnexpectedValueException When the value is neither null, 0 nor 1.
     */
    public static function nullableBool(array $row, string $column): ?bool
    {
        $value = self::value($row, $column);

        return $value === null ? null : self::toBool($value, $column);
    }

    /**
     * Reads a `DATETIME` column, parsed as `Y-m-d H:i:s` (optionally with microseconds) in UTC.
     *
     * @param array<string, mixed> $row    Row keyed by column name.
     * @param string               $column Column name.
     *
     * @return DateTimeImmutable
     *
     * @throws LogicException           When the column is missing.
     * @throws UnexpectedValueException When the value is not a valid date and time.
     */
    public static function dateTime(array $row, string $column): DateTimeImmutable
    {
        return self::toDateTime(self::required($row, $column), $column);
    }

    /**
     * Reads a nullable `DATETIME` column, in UTC.
     *
     * @param array<string, mixed> $row    Row keyed by column name.
     * @param string               $column Column name.
     *
     * @return DateTimeImmutable|null
     *
     * @throws LogicException           When the column is missing.
     * @throws UnexpectedValueException When the value is neither null nor a valid date and time.
     */
    public static function nullableDateTime(array $row, string $column): ?DateTimeImmutable
    {
        $value = self::value($row, $column);

        return $value === null ? null : self::toDateTime($value, $column);
    }

    /**
     * Reads an `ENUM` (or fixed-set `VARCHAR`) column as a backed enum case.
     *
     * @template T of BackedEnum
     *
     * @param array<string, mixed> $row    Row keyed by column name.
     * @param string               $column Column name.
     * @param string               $enum   Backed enum class name.
     *
     * @phpstan-param class-string<T> $enum
     *
     * @return T
     *
     * @throws LogicException           When the column is missing.
     * @throws UnexpectedValueException When the value is not a case of the enum.
     */
    public static function enum(array $row, string $column, string $enum): BackedEnum
    {
        return self::toEnum(self::required($row, $column), $column, $enum);
    }

    /**
     * Reads a nullable `ENUM` column as a backed enum case.
     *
     * @template T of BackedEnum
     *
     * @param array<string, mixed> $row    Row keyed by column name.
     * @param string               $column Column name.
     * @param string               $enum   Backed enum class name.
     *
     * @phpstan-param class-string<T> $enum
     *
     * @return T|null
     *
     * @throws LogicException           When the column is missing.
     * @throws UnexpectedValueException When the value is neither null nor a case of the enum.
     */
    public static function nullableEnum(array $row, string $column, string $enum): ?BackedEnum
    {
        $value = self::value($row, $column);

        return $value === null ? null : self::toEnum($value, $column, $enum);
    }

    /**
     * Reads a `JSON` column holding a list of strings.
     *
     * @param array<string, mixed> $row    Row keyed by column name.
     * @param string               $column Column name.
     *
     * @return list<string>
     *
     * @throws LogicException           When the column is missing.
     * @throws UnexpectedValueException When the value is not a JSON list of strings.
     */
    public static function stringList(array $row, string $column): array
    {
        return self::toStringList(self::required($row, $column), $column);
    }

    /**
     * Reads a nullable `JSON` column holding a list of strings.
     *
     * @param array<string, mixed> $row    Row keyed by column name.
     * @param string               $column Column name.
     *
     * @return list<string>|null
     *
     * @throws LogicException           When the column is missing.
     * @throws UnexpectedValueException When the value is neither null nor a JSON list of strings.
     */
    public static function nullableStringList(array $row, string $column): ?array
    {
        $value = self::value($row, $column);

        return $value === null ? null : self::toStringList($value, $column);
    }

    /**
     * Returns the value of a column that must be present (null included).
     *
     * @param array<string, mixed> $row    Row keyed by column name.
     * @param string               $column Column name.
     *
     * @return mixed
     *
     * @throws LogicException When the column is missing.
     */
    private static function value(array $row, string $column): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new LogicException(self::context() . ": missing column '{$column}'");
        }

        return $row[$column];
    }

    /**
     * Returns the value of a column that must be present and not null.
     *
     * @param array<string, mixed> $row    Row keyed by column name.
     * @param string               $column Column name.
     *
     * @return mixed
     *
     * @throws LogicException           When the column is missing.
     * @throws UnexpectedValueException When the value is null.
     */
    private static function required(array $row, string $column): mixed
    {
        $value = self::value($row, $column);

        if ($value === null) {
            throw self::invalid($column, 'a non-null value');
        }

        return $value;
    }

    /**
     * Converts a value to an integer.
     *
     * @param mixed  $value  Raw value.
     * @param string $column Column name.
     *
     * @return integer
     *
     * @throws UnexpectedValueException When the value is not an integer.
     */
    private static function toInt(mixed $value, string $column): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?[0-9]+$/', $value) === 1) {
            $int = (int) $value;

            if ((string) $int === $value) {
                return $int;
            }
        }

        throw self::invalid($column, 'an integer');
    }

    /**
     * Checks that a value is a string.
     *
     * @param mixed  $value  Raw value.
     * @param string $column Column name.
     *
     * @return string
     *
     * @throws UnexpectedValueException When the value is not a string.
     */
    private static function toString(mixed $value, string $column): string
    {
        if (!is_string($value)) {
            throw self::invalid($column, 'a string');
        }

        return $value;
    }

    /**
     * Converts a `TINYINT(1)` value to a boolean.
     *
     * @param mixed  $value  Raw value.
     * @param string $column Column name.
     *
     * @return boolean
     *
     * @throws UnexpectedValueException When the value is not 0 or 1.
     */
    private static function toBool(mixed $value, string $column): bool
    {
        return match ($value) {
            0, '0', false => false,
            1, '1', true => true,
            default => throw self::invalid($column, 'a boolean (0 or 1)'),
        };
    }

    /**
     * Parses a `DATETIME` value in UTC.
     *
     * @param mixed  $value  Raw value.
     * @param string $column Column name.
     *
     * @return DateTimeImmutable
     *
     * @throws UnexpectedValueException When the value is not a valid date and time.
     */
    private static function toDateTime(mixed $value, string $column): DateTimeImmutable
    {
        if (is_string($value)) {
            $utc = new DateTimeZone('UTC');

            foreach (self::DATE_TIME_FORMATS as $format) {
                $dateTime = DateTimeImmutable::createFromFormat($format, $value, $utc);

                if ($dateTime !== false && DateTimeImmutable::getLastErrors() === false) {
                    return $dateTime;
                }
            }
        }

        throw self::invalid($column, 'a date and time (Y-m-d H:i:s)');
    }

    /**
     * Converts a value to a case of a backed enum.
     *
     * @template T of BackedEnum
     *
     * @param mixed  $value  Raw value.
     * @param string $column Column name.
     * @param string $enum   Backed enum class name.
     *
     * @phpstan-param class-string<T> $enum
     *
     * @return T
     *
     * @throws UnexpectedValueException When the value is not a case of the enum.
     */
    private static function toEnum(mixed $value, string $column, string $enum): BackedEnum
    {
        $case = is_string($value) || is_int($value) ? $enum::tryFrom($value) : null;

        if ($case === null) {
            throw self::invalid($column, "a case of {$enum}");
        }

        return $case;
    }

    /**
     * Decodes a JSON list of strings.
     *
     * @param mixed  $value  Raw value.
     * @param string $column Column name.
     *
     * @return list<string>
     *
     * @throws UnexpectedValueException When the value is not a JSON list of strings.
     */
    private static function toStringList(mixed $value, string $column): array
    {
        $expected = 'a JSON list of strings';

        if (!is_string($value)) {
            throw self::invalid($column, $expected);
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw self::invalid($column, $expected);
        }

        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw self::invalid($column, $expected);
        }

        foreach ($decoded as $item) {
            if (!is_string($item)) {
                throw self::invalid($column, $expected);
            }
        }

        return $decoded;
    }

    /**
     * Builds the exception for a value of the wrong shape, without the value itself.
     *
     * @param string $column   Column name.
     * @param string $expected Description of the expected value.
     *
     * @return UnexpectedValueException
     */
    private static function invalid(string $column, string $expected): UnexpectedValueException
    {
        return new UnexpectedValueException(self::context() . ": column '{$column}' must hold {$expected}");
    }

    /**
     * Names the class that asked for the conversion (usually the entity), for error messages.
     *
     * Reads the call stack without arguments, so no row value can leak into the name.
     *
     * @return string
     */
    private static function context(): string
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            $class = $frame['class'] ?? null;

            if ($class !== null && $class !== self::class) {
                return $class;
            }
        }

        return 'Row';
    }
}
