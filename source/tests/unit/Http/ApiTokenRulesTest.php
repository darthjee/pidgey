<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\ApiTokenRules;
use Pidgey\Http\FieldRules;
use Pidgey\Http\HttpException;
use Pidgey\Http\ValidationFailedException;

#[CoversClass(ApiTokenRules::class)]
#[UsesClass(FieldRules::class)]
#[UsesClass(ValidationFailedException::class)]
#[UsesClass(HttpException::class)]
final class ApiTokenRulesTest extends TestCase
{
    public function testTrimsTheNameAndKeepsTheScopes(): void
    {
        $this->assertSame(
            ['name' => 'Moria', 'scopes' => ['send']],
            ApiTokenRules::forCreate(['name' => ' Moria ', 'scopes' => ['send'], 'extra' => 1]),
        );
    }

    public function testAccepts255MultibyteCharacters(): void
    {
        $name = str_repeat('é', 255);

        $this->assertSame($name, ApiTokenRules::forCreate(['name' => $name, 'scopes' => ['send']])['name']);
    }

    public function testDeduplicatesScopes(): void
    {
        $this->assertSame(
            ['send'],
            ApiTokenRules::forCreate(['name' => 'a', 'scopes' => ['send', 'send']])['scopes'],
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidNames')]
    public function testRejectsInvalidNames(array $body): void
    {
        $this->assertSame(
            [$this->messageOf($body)],
            $this->detailsOf(['scopes' => ['send']] + $body),
        );
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidNames(): array
    {
        return [
            'blank' => [['name' => '   ']],
            'missing' => [[]],
            'integer' => [['name' => 42]],
            'null' => [['name' => null]],
            'array' => [['name' => []]],
            'too long' => [['name' => str_repeat('a', 256)]],
        ];
    }

    #[DataProvider('invalidScopes')]
    public function testRejectsInvalidScopes(mixed $scopes, string $message): void
    {
        $this->assertSame([$message], $this->detailsOf(['name' => 'a', 'scopes' => $scopes]));
    }

    public function testRejectsMissingScopes(): void
    {
        $this->assertSame([ApiTokenRules::SCOPES_NOT_ARRAY], $this->detailsOf(['name' => 'a']));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidScopes(): array
    {
        return [
            'string' => ['send', ApiTokenRules::SCOPES_NOT_ARRAY],
            'object' => [['0' => 'send', 'x' => 'send'], ApiTokenRules::SCOPES_NOT_ARRAY],
            'non-list' => [[1 => 'send'], ApiTokenRules::SCOPES_NOT_ARRAY],
            'empty' => [[], ApiTokenRules::SCOPES_EMPTY],
            'integer' => [[1], ApiTokenRules::SCOPES_UNKNOWN],
            'null element' => [[null], ApiTokenRules::SCOPES_UNKNOWN],
            'nested' => [[['send']], ApiTokenRules::SCOPES_UNKNOWN],
            'unknown' => [['admin'], ApiTokenRules::SCOPES_UNKNOWN],
            'case' => [['Send'], ApiTokenRules::SCOPES_UNKNOWN],
            'padded' => [[' send '], ApiTokenRules::SCOPES_UNKNOWN],
        ];
    }

    public function testReportsNameAndScopesErrorsTogether(): void
    {
        $details = $this->detailsOf(['name' => '', 'scopes' => []]);

        $this->assertCount(2, $details);
        $this->assertSame(ApiTokenRules::SCOPES_EMPTY, $details[1]);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<string>
     */
    private function detailsOf(array $body): array
    {
        try {
            ApiTokenRules::forCreate($body);
        } catch (ValidationFailedException $exception) {
            return $exception->details() ?? [];
        }

        $this->fail('Expected a ValidationFailedException.');
    }

    /**
     * @param array<string, mixed> $body
     */
    private function messageOf(array $body): string
    {
        $name = $body['name'] ?? null;

        return match (true) {
            !array_key_exists('name', $body), $name === null, trim((string) (is_string($name) ? $name : 'x')) === ''
                => 'name should not be empty',
            !is_string($name) => 'name must be a string',
            default => 'name must be shorter than or equal to 255 characters',
        };
    }
}
