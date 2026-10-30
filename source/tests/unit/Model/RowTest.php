<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Model;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Model\Row;
use Pidgey\Tests\Support\SampleStatus;
use Throwable;
use UnexpectedValueException;

#[CoversClass(Row::class)]
final class RowTest extends TestCase
{
    private const SENSITIVE_VALUE = 'value-that-must-not-leak';

    public function testInt(): void
    {
        $this->assertSame(42, Row::int(['id' => 42], 'id'));
    }

    public function testIntCastsNumericString(): void
    {
        $this->assertSame(-7, Row::int(['id' => '-7'], 'id'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidInts(): array
    {
        return [
            'float' => [1.5],
            'decimal string' => ['1.5'],
            'word' => ['abc'],
            'overflow' => ['99999999999999999999'],
            'bool' => [true],
        ];
    }

    #[DataProvider('invalidInts')]
    public function testIntRejectsNonInteger(mixed $value): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("column 'id' must hold an integer");

        Row::int(['id' => $value], 'id');
    }

    public function testNullableInt(): void
    {
        $this->assertNull(Row::nullableInt(['id' => null], 'id'));
        $this->assertSame(3, Row::nullableInt(['id' => 3], 'id'));
    }

    public function testString(): void
    {
        $this->assertSame('alice', Row::string(['username' => 'alice'], 'username'));
    }

    public function testStringKeepsBinaryBytes(): void
    {
        $bytes = "\x00\xff\x10";

        $this->assertSame($bytes, Row::string(['password' => $bytes], 'password'));
    }

    public function testStringRejectsNonString(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("column 'username' must hold a string");

        Row::string(['username' => 12], 'username');
    }

    public function testNullableString(): void
    {
        $this->assertNull(Row::nullableString(['name' => null], 'name'));
        $this->assertSame('x', Row::nullableString(['name' => 'x'], 'name'));
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function bools(): array
    {
        return [
            'int 0' => [0, false],
            'int 1' => [1, true],
            'string 0' => ['0', false],
            'string 1' => ['1', true],
            'false' => [false, false],
            'true' => [true, true],
        ];
    }

    #[DataProvider('bools')]
    public function testBool(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, Row::bool(['is_admin' => $value], 'is_admin'));
    }

    public function testBoolRejectsOtherValues(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("column 'is_admin' must hold a boolean");

        Row::bool(['is_admin' => 2], 'is_admin');
    }

    public function testNullableBool(): void
    {
        $this->assertNull(Row::nullableBool(['active' => null], 'active'));
        $this->assertTrue(Row::nullableBool(['active' => 1], 'active'));
    }

    public function testDateTimeIsParsedInUtc(): void
    {
        $dateTime = Row::dateTime(['created_at' => '2026-03-04 05:06:07'], 'created_at');

        $this->assertSame('2026-03-04T05:06:07+00:00', $dateTime->format(DATE_ATOM));
        $this->assertSame('UTC', $dateTime->getTimezone()->getName());
        $this->assertSame(
            (new DateTimeImmutable('2026-03-04 05:06:07+00:00'))->getTimestamp(),
            $dateTime->getTimestamp(),
        );
    }

    public function testDateTimeIgnoresDefaultTimeZone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/Sao_Paulo');

        try {
            $dateTime = Row::dateTime(['created_at' => '2026-03-04 05:06:07'], 'created_at');
        } finally {
            date_default_timezone_set($previous);
        }

        $this->assertSame('2026-03-04T05:06:07+00:00', $dateTime->format(DATE_ATOM));
    }

    public function testDateTimeAcceptsMicroseconds(): void
    {
        $dateTime = Row::dateTime(['applied_at' => '2026-03-04 05:06:07.123456'], 'applied_at');

        $this->assertSame('2026-03-04 05:06:07.123456', $dateTime->format('Y-m-d H:i:s.u'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidDateTimes(): array
    {
        return [
            'garbage' => ['not a date'],
            'date only' => ['2026-03-04'],
            'impossible day' => ['2026-02-30 00:00:00'],
            'int' => [1700000000],
        ];
    }

    #[DataProvider('invalidDateTimes')]
    public function testDateTimeRejectsInvalidValues(mixed $value): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("column 'created_at' must hold a date and time");

        Row::dateTime(['created_at' => $value], 'created_at');
    }

    public function testNullableDateTime(): void
    {
        $this->assertNull(Row::nullableDateTime(['used_at' => null], 'used_at'));
        $this->assertSame(
            '2026-01-02 03:04:05',
            Row::nullableDateTime(['used_at' => '2026-01-02 03:04:05'], 'used_at')?->format('Y-m-d H:i:s'),
        );
    }

    public function testEnum(): void
    {
        $this->assertSame(SampleStatus::Approved, Row::enum(['status' => 'approved'], 'status', SampleStatus::class));
    }

    public function testEnumRejectsUnknownValue(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("column 'status' must hold a case of " . SampleStatus::class);

        Row::enum(['status' => 'archived'], 'status', SampleStatus::class);
    }

    public function testEnumRejectsNonScalar(): void
    {
        $this->expectException(UnexpectedValueException::class);

        Row::enum(['status' => ['approved']], 'status', SampleStatus::class);
    }

    public function testNullableEnum(): void
    {
        $this->assertNull(Row::nullableEnum(['status' => null], 'status', SampleStatus::class));
        $this->assertSame(
            SampleStatus::Pending,
            Row::nullableEnum(['status' => 'pending'], 'status', SampleStatus::class),
        );
    }

    public function testStringList(): void
    {
        $this->assertSame(['a', 'b'], Row::stringList(['scopes' => '["a","b"]'], 'scopes'));
        $this->assertSame([], Row::stringList(['scopes' => '[]'], 'scopes'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidStringLists(): array
    {
        return [
            'invalid json' => ['["a",'],
            'object' => ['{"a":"b"}'],
            'scalar' => ['"a"'],
            'non-string item' => ['["a",1]'],
            'not a string' => [12],
        ];
    }

    #[DataProvider('invalidStringLists')]
    public function testStringListRejectsInvalidValues(mixed $value): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("column 'scopes' must hold a JSON list of strings");

        Row::stringList(['scopes' => $value], 'scopes');
    }

    public function testNullableStringList(): void
    {
        $this->assertNull(Row::nullableStringList(['scopes' => null], 'scopes'));
        $this->assertSame(['x'], Row::nullableStringList(['scopes' => '["x"]'], 'scopes'));
    }

    public function testMissingColumnThrowsNamingColumnAndCaller(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(self::class . ": missing column 'email'");

        Row::string(['username' => 'alice'], 'email');
    }

    public function testNullableVariantsStillRequireTheColumn(): void
    {
        $this->expectException(LogicException::class);

        Row::nullableString([], 'email');
    }

    public function testNullInNonNullableColumnThrows(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("column 'id' must hold a non-null value");

        Row::int(['id' => null], 'id');
    }

    /**
     * @return array<string, array{callable(array<string, mixed>, string): mixed}>
     */
    public static function conversions(): array
    {
        return [
            'int' => [Row::int(...)],
            'bool' => [static fn (array $row, string $column): mixed => Row::bool($row, $column)],
            'dateTime' => [Row::dateTime(...)],
            'enum' => [static fn (array $row, string $column): mixed => Row::enum($row, $column, SampleStatus::class)],
            'stringList' => [Row::stringList(...)],
        ];
    }

    /**
     * @param callable(array<string, mixed>, string): mixed $convert
     */
    #[DataProvider('conversions')]
    public function testMessagesNeverContainTheValue(callable $convert): void
    {
        try {
            $convert(['password_digest' => self::SENSITIVE_VALUE], 'password_digest');
            $this->fail('Expected the conversion to fail.');
        } catch (UnexpectedValueException $exception) {
            $this->assertStringContainsString('password_digest', $exception->getMessage());
            $this->assertNoSensitiveValue($exception);
        }
    }

    private function assertNoSensitiveValue(Throwable $exception): void
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            $this->assertStringNotContainsString(self::SENSITIVE_VALUE, $current->getMessage());
        }
    }
}
