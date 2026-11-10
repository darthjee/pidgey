<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\IntegerRules;

#[CoversClass(IntegerRules::class)]
final class IntegerRulesTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function requiredProvider(): array
    {
        return [
            'absent' => [[], ['port should not be empty']],
            'null' => [['port' => null], ['port should not be empty']],
            'integer' => [['port' => 587], []],
            'float without fraction' => [['port' => 587.0], ['port must be an integer']],
            'float with fraction' => [['port' => 587.5], ['port must be an integer']],
            'numeric string' => [['port' => '587'], ['port must be an integer']],
            'empty string' => [['port' => ''], ['port must be an integer']],
            'true' => [['port' => true], ['port must be an integer']],
            'false' => [['port' => false], ['port must be an integer']],
            'array' => [['port' => [587]], ['port must be an integer']],
            'out of range string is only a type error' => [['port' => '0'], ['port must be an integer']],
            'out of range float is only a type error' => [['port' => 0.0], ['port must be an integer']],
            'lower bound' => [['port' => 1], []],
            'upper bound' => [['port' => 65535], []],
            'below the range' => [['port' => 0], ['port must be between 1 and 65535']],
            'above the range' => [['port' => 65536], ['port must be between 1 and 65535']],
            'negative' => [['port' => -1], ['port must be between 1 and 65535']],
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string> $messages
     */
    #[DataProvider('requiredProvider')]
    public function testRequiredFieldWithRange(array $body, array $messages): void
    {
        $rules = (new IntegerRules('port'))->range(1, 65535);

        $this->assertSame($messages, $rules->errors($body));
        $this->assertFalse($rules->isOmitted($body));
    }

    public function testWithoutRangeAnyIntegerPasses(): void
    {
        $rules = new IntegerRules('n');

        $this->assertSame([], $rules->errors(['n' => PHP_INT_MIN]));
        $this->assertSame([], $rules->errors(['n' => PHP_INT_MAX]));
    }

    public function testRangeMessageUsesNegativeBoundsAsDecimals(): void
    {
        $rules = (new IntegerRules('n'))->range(-5, 5);

        $this->assertSame(['n must be between -5 and 5'], $rules->errors(['n' => -6]));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function notPresentProvider(): array
    {
        return [
            'absent' => [[]],
            'null' => [['port' => null]],
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('notPresentProvider')]
    public function testOptionalFieldNotPresentIsOmitted(array $body): void
    {
        $rules = (new IntegerRules('port'))->optional()->range(1, 65535);

        $this->assertSame([], $rules->errors($body));
        $this->assertTrue($rules->isOmitted($body));
    }

    public function testOptionalFieldPresentRunsEveryRule(): void
    {
        $rules = (new IntegerRules('port'))->optional()->range(1, 65535);

        $this->assertSame(['port must be an integer'], $rules->errors(['port' => '25']));
        $this->assertSame(['port must be between 1 and 65535'], $rules->errors(['port' => 0]));
        $this->assertFalse($rules->isOmitted(['port' => 0]));
    }

    public function testValueReturnsTheInteger(): void
    {
        $this->assertSame(465, (new IntegerRules('port'))->value(['port' => 465]));
    }

    public function testRuleMethodsReturnTheBuilder(): void
    {
        $rules = new IntegerRules('port');

        $this->assertSame($rules, $rules->optional());
        $this->assertSame($rules, $rules->range(1, 2));
    }
}
