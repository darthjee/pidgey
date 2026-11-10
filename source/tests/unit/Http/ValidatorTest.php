<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\FieldRules;
use Pidgey\Http\HostName;
use Pidgey\Http\HttpException;
use Pidgey\Http\IntegerRules;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Http\Validator;

#[CoversClass(Validator::class)]
#[CoversClass(FieldRules::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(HostName::class)]
#[UsesClass(IntegerRules::class)]
#[UsesClass(ValidationFailedException::class)]
final class ValidatorTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function singleMessageProvider(): array
    {
        return [
            'absent' => [[], 'name should not be empty'],
            'null' => [['name' => null], 'name should not be empty'],
            'integer' => [['name' => 1], 'name must be a string'],
            'float' => [['name' => 1.5], 'name must be a string'],
            'array' => [['name' => ['a']], 'name must be a string'],
            'object' => [['name' => ['a' => 'b']], 'name must be a string'],
            'boolean' => [['name' => false], 'name must be a string'],
            'empty' => [['name' => ''], 'name should not be empty'],
            'whitespace only' => [['name' => " \t\n "], 'name should not be empty'],
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('singleMessageProvider')]
    public function testMissingWrongTypeOrEmptyGetsExactlyOneMessage(array $body, string $message): void
    {
        $validator = new Validator($body);
        $validator->string('name')->trim()->notEmpty()->email()->minLength(300)->maxLength(0)->maxBytes(0);

        $this->assertSame([$message], $this->details($validator));
    }

    public function testAbsentFieldIsEmptyEvenWithoutNotEmpty(): void
    {
        $validator = new Validator([]);
        $validator->string('token');

        $this->assertSame(['token should not be empty'], $this->details($validator));
    }

    public function testEmptyValueWithoutNotEmptyRunsTheOtherRules(): void
    {
        $validator = new Validator(['token' => '']);
        $validator->string('token')->minLength(1);

        $this->assertSame(['token must be longer than or equal to 1 characters'], $this->details($validator));
    }

    /**
     * @return array<string, array{callable(FieldRules): FieldRules, string, string|null}>
     */
    public static function ruleProvider(): array
    {
        $email = static fn (FieldRules $r): FieldRules => $r->email();
        $min = static fn (FieldRules $r): FieldRules => $r->minLength(3);
        $max = static fn (FieldRules $r): FieldRules => $r->maxLength(3);
        $bytes = static fn (FieldRules $r): FieldRules => $r->maxBytes(3);
        $nul = static fn (FieldRules $r): FieldRules => $r->noNulByte();
        $oneOf = static fn (FieldRules $r): FieldRules => $r->oneOf('none', 'tls', 'ssl');
        $host = static fn (FieldRules $r): FieldRules => $r->hostname();
        $control = static fn (FieldRules $r): FieldRules => $r->noControlCharacters();
        $breaks = static fn (FieldRules $r): FieldRules => $r->noLineBreaks();
        $other = static fn (FieldRules $r): FieldRules => $r->noOtherControlCharacters();
        $otherMessage = 'f must not contain control characters';
        $oneOfMessage = 'f must be one of the following values: none, tls, ssl';

        return [
            'email passes' => [$email, 'bob@example.com', null],
            'email fails' => [$email, 'bob', 'f must be an email'],
            'email rejects a Unicode local part' => [$email, 'bób@example.com', 'f must be an email'],
            'min length at the limit' => [$min, 'abc', null],
            'min length counts characters' => [$min, 'äöü', null],
            'min length fails' => [$min, 'ab', 'f must be longer than or equal to 3 characters'],
            'max length at the limit' => [$max, 'abc', null],
            'max length counts characters' => [$max, 'äöü', null],
            'max length fails' => [$max, 'abcd', 'f must be shorter than or equal to 3 characters'],
            'max bytes at the limit' => [$bytes, 'abc', null],
            'max bytes counts bytes' => [$bytes, 'äö', 'f must be shorter than or equal to 3 bytes'],
            'no NUL byte passes' => [$nul, 'abc', null],
            'no NUL byte fails' => [$nul, "a\0b", 'f must not contain a NUL byte'],
            'one of passes' => [$oneOf, 'tls', null],
            'one of fails' => [$oneOf, 'starttls', $oneOfMessage],
            'one of is case-sensitive' => [$oneOf, 'TLS', $oneOfMessage],
            'one of rejects an empty value' => [$oneOf, '', $oneOfMessage],
            'hostname passes' => [$host, 'smtp.example.com', null],
            'hostname passes an IPv6 literal' => [$host, '[::1]', null],
            'hostname fails' => [$host, 'smtp.exämple.de', 'f must be a hostname or an IP address'],
            'hostname fails on a space' => [$host, 'smtp example.com', 'f must be a hostname or an IP address'],
            'no control characters passes' => [$control, 'relay-user ä', null],
            'no control characters fails on a tab' => [$control, "a\tb", 'f must not contain control characters'],
            'no control characters fails on a NUL' => [$control, "a\0b", 'f must not contain control characters'],
            'no control characters fails on CR' => [$control, "a\rb", 'f must not contain control characters'],
            'no control characters fails on LF' => [$control, "a\nb", 'f must not contain control characters'],
            'no control characters fails on US' => [$control, "a\x1Fb", 'f must not contain control characters'],
            'no control characters fails on DEL' => [$control, "a\x7Fb", 'f must not contain control characters'],
            'no line breaks passes' => [$breaks, "a\tb", null],
            'no other control characters passes' => [$other, 'relay-user ä', null],
            'no other control characters ignores CR' => [$other, "a\rb", null],
            'no other control characters ignores LF' => [$other, "a\nb", null],
            'no other control characters fails on a NUL' => [$other, "a\0b", $otherMessage],
            'no other control characters fails on SOH' => [$other, "a\x01b", $otherMessage],
            'no other control characters fails on a tab' => [$other, "a\tb", $otherMessage],
            'no other control characters fails on VT' => [$other, "a\x0Bb", $otherMessage],
            'no other control characters fails on FF' => [$other, "a\x0Cb", $otherMessage],
            'no other control characters fails on SO' => [$other, "a\x0Eb", $otherMessage],
            'no other control characters fails on DEL' => [$other, "a\x7Fb", $otherMessage],
            'no line breaks fails on CR' => [$breaks, "a\rb", 'f must not contain line breaks'],
            'no line breaks fails on LF' => [$breaks, "a\nb", 'f must not contain line breaks'],
            'no line breaks fails on CRLF' => [$breaks, "a\r\nb", 'f must not contain line breaks'],
        ];
    }

    /**
     * @param callable(FieldRules): FieldRules $declare
     */
    #[DataProvider('ruleProvider')]
    public function testRule(callable $declare, string $value, ?string $message): void
    {
        $validator = new Validator(['f' => $value]);
        $declare($validator->string('f'));

        if ($message === null) {
            $this->assertSame(['f' => $value], $validator->validated());

            return;
        }

        $this->assertSame([$message], $this->details($validator));
    }

    public function testRuleMethodsReturnTheBuilder(): void
    {
        $rules = (new Validator([]))->string('f');

        $this->assertSame($rules, $rules->trim());
        $this->assertSame($rules, $rules->notEmpty());
        $this->assertSame($rules, $rules->email());
        $this->assertSame($rules, $rules->minLength(1));
        $this->assertSame($rules, $rules->maxLength(1));
        $this->assertSame($rules, $rules->maxBytes(1));
        $this->assertSame($rules, $rules->noNulByte());
        $this->assertSame($rules, $rules->oneOf('a'));
        $this->assertSame($rules, $rules->hostname());
        $this->assertSame($rules, $rules->noControlCharacters());
        $this->assertSame($rules, $rules->noLineBreaks());
        $this->assertSame($rules, $rules->noOtherControlCharacters());
    }

    public function testIntegerFieldReturnsItsRules(): void
    {
        $validator = new Validator(['port' => 587]);

        $this->assertInstanceOf(IntegerRules::class, $validator->integer('port'));
    }

    public function testStringAndIntegerFieldsMix(): void
    {
        $validator = new Validator([
            'host' => ' smtp.example.com ',
            'port' => 587,
            'encryption' => 'tls',
            'timeout' => null,
        ]);
        $validator->string('host')->trim()->notEmpty()->hostname();
        $validator->integer('port')->range(1, 65535);
        $validator->string('encryption')->trim()->notEmpty()->oneOf('none', 'tls', 'ssl');
        $validator->integer('timeout')->optional();

        $this->assertSame(
            ['host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls'],
            $validator->validated(),
        );
    }

    public function testStringAndIntegerFailuresAreReportedInDeclarationOrder(): void
    {
        $validator = new Validator([
            'name' => '',
            'port' => '587',
            'encryption' => 'starttls',
            'retries' => 0,
        ]);
        $validator->string('name')->trim()->notEmpty();
        $validator->integer('port')->range(1, 65535);
        $validator->string('encryption')->oneOf('none', 'tls', 'ssl');
        $validator->integer('retries')->range(1, 3);

        $this->assertSame(
            [
                'name should not be empty',
                'port must be an integer',
                'encryption must be one of the following values: none, tls, ssl',
                'retries must be between 1 and 3',
            ],
            $this->details($validator),
        );
    }

    public function testDeclaringAnIntegerFieldTwiceIsALogicError(): void
    {
        $validator = new Validator([]);
        $validator->string('f');

        $this->expectException(LogicException::class);

        $validator->integer('f');
    }

    public function testTrimmedFieldReturnsTheTrimmedValue(): void
    {
        $validator = new Validator(['username' => "  bob\t\n"]);
        $validator->string('username')->trim()->notEmpty()->maxLength(3);

        $this->assertSame(['username' => 'bob'], $validator->validated());
    }

    public function testRulesRunOnTheTrimmedValue(): void
    {
        $validator = new Validator(['email' => ' bob@example.com ']);
        $validator->string('email')->trim()->notEmpty()->email();

        $this->assertSame(['email' => 'bob@example.com'], $validator->validated());
    }

    public function testUntrimmedFieldKeepsSurroundingSpaces(): void
    {
        $password = '  ' . str_repeat('a', 8) . '  ';
        $validator = new Validator(['password' => $password]);
        $validator->string('password')->notEmpty()->minLength(8)->maxBytes(72)->noNulByte();

        $this->assertSame(['password' => $password], $validator->validated());
    }

    public function testWhitespaceOnlyUntrimmedFieldIsNotEmpty(): void
    {
        $validator = new Validator(['password' => '        ']);
        $validator->string('password')->notEmpty()->minLength(8);

        $this->assertSame(['password' => '        '], $validator->validated());
    }

    public function testCharacterAndByteLimitsDiffer(): void
    {
        $password = str_repeat('é', 37);
        $validator = new Validator(['password' => $password]);
        $validator->string('password')->minLength(8)->maxLength(40)->maxBytes(72)->noNulByte();

        $this->assertSame(
            ['password must be shorter than or equal to 72 bytes'],
            $this->details($validator),
        );
    }

    public function testMultiByteValueAtTheByteLimitPasses(): void
    {
        $password = str_repeat('é', 36);
        $validator = new Validator(['password' => $password]);
        $validator->string('password')->minLength(8)->maxBytes(72);

        $this->assertSame(72, strlen($password));
        $this->assertSame(['password' => $password], $validator->validated());
    }

    public function testEveryFailureIsReportedInDeclarationOrder(): void
    {
        $validator = new Validator([
            'username' => '   ',
            'email' => str_repeat('x', 256),
            'password' => "ab\0",
            'organizationName' => 7,
        ]);
        $validator->string('username')->trim()->notEmpty()->maxLength(255);
        $validator->string('email')->trim()->notEmpty()->email()->maxLength(255);
        $validator->string('password')->notEmpty()->minLength(8)->maxBytes(72)->noNulByte();
        $validator->string('organizationName')->trim()->notEmpty()->maxLength(255);

        $expected = [
            'username should not be empty',
            'email must be an email',
            'email must be shorter than or equal to 255 characters',
            'password must be longer than or equal to 8 characters',
            'password must not contain a NUL byte',
            'organizationName must be a string',
        ];

        try {
            $validator->validated();
            $this->fail('Expected a ValidationFailedException');
        } catch (ValidationFailedException $error) {
            $this->assertSame($expected, $error->details());
            $this->assertSame(implode('; ', $expected), $error->errorMessage());
            $this->assertSame('VALIDATION_FAILED', $error->errorCode());
            $this->assertSame(400, $error->status());
        }
    }

    public function testRulesRunInTheirDeclarationOrder(): void
    {
        $validator = new Validator(['f' => "a\0"]);
        $validator->string('f')->noNulByte()->minLength(5);

        $this->assertSame(
            ['f must not contain a NUL byte', 'f must be longer than or equal to 5 characters'],
            $this->details($validator),
        );
    }

    public function testRegisterBodyPassesAndDropsUnknownFields(): void
    {
        $password = str_repeat('a', 10);
        $validator = new Validator([
            'username' => 'bob',
            'email' => 'bob@example.com',
            'password' => $password,
            'organizationName' => "Bob's Shop",
            'password_confirmation' => $password,
            'isAdmin' => true,
        ]);
        $validator->string('username')->trim()->notEmpty()->maxLength(255);
        $validator->string('email')->trim()->notEmpty()->email()->maxLength(255);
        $validator->string('password')->notEmpty()->minLength(8)->maxBytes(72)->noNulByte();
        $validator->string('organizationName')->trim()->notEmpty()->maxLength(255);

        $this->assertSame(
            [
                'username' => 'bob',
                'email' => 'bob@example.com',
                'password' => $password,
                'organizationName' => "Bob's Shop",
            ],
            $validator->validated(),
        );
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function notPresentProvider(): array
    {
        return [
            'absent' => [[]],
            'null' => [['email' => null]],
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('notPresentProvider')]
    public function testOptionalFieldNotPresentIsOmitted(array $body): void
    {
        $validator = new Validator($body);
        $validator->string('email')->optional()->trim()->notEmpty()->email()->maxLength(255);

        $this->assertSame([], $validator->validated());
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function optionalFailureProvider(): array
    {
        return [
            'empty' => ['', 'email should not be empty'],
            'whitespace only' => ['   ', 'email should not be empty'],
            'integer' => [1, 'email must be a string'],
            'boolean' => [false, 'email must be a string'],
            'array' => [['a'], 'email must be a string'],
            'invalid' => ['bob', 'email must be an email'],
        ];
    }

    #[DataProvider('optionalFailureProvider')]
    public function testOptionalFieldPresentRunsEveryRule(mixed $value, string $message): void
    {
        $validator = new Validator(['email' => $value]);
        $validator->string('email')->optional()->trim()->notEmpty()->email()->maxLength(255);

        $this->assertSame([$message], $this->details($validator));
    }

    public function testOptionalFieldPresentAndValidIsReturned(): void
    {
        $validator = new Validator(['email' => ' bob@example.com ']);
        $validator->string('email')->optional()->trim()->notEmpty()->email();

        $this->assertSame(['email' => 'bob@example.com'], $validator->validated());
    }

    public function testOptionalMethodReturnsTheBuilder(): void
    {
        $rules = (new Validator([]))->string('f');

        $this->assertSame($rules, $rules->optional());
    }

    public function testOptionalAndRequiredFieldsMix(): void
    {
        $current = bin2hex(random_bytes(3));
        $replacement = bin2hex(random_bytes(7));
        $validator = new Validator([
            'currentPassword' => $current,
            'email' => null,
            'newPassword' => $replacement,
        ]);
        $validator->string('currentPassword')->notEmpty();
        $validator->string('username')->optional()->trim()->notEmpty()->maxLength(255);
        $validator->string('email')->optional()->trim()->notEmpty()->email()->maxLength(255);
        $validator->string('newPassword')->optional()->minLength(8)->maxBytes(72)->noNulByte();

        $this->assertSame(
            ['currentPassword' => $current, 'newPassword' => $replacement],
            $validator->validated(),
        );
    }

    public function testOptionalAndRequiredFailuresAreReportedInDeclarationOrder(): void
    {
        $validator = new Validator([
            'username' => '',
            'newPassword' => implode(chr(0), ['ab', '']),
        ]);
        $validator->string('currentPassword')->notEmpty();
        $validator->string('username')->optional()->trim()->notEmpty()->maxLength(255);
        $validator->string('email')->optional()->trim()->notEmpty()->email()->maxLength(255);
        $validator->string('newPassword')->optional()->minLength(8)->maxBytes(72)->noNulByte();

        $this->assertSame(
            [
                'currentPassword should not be empty',
                'username should not be empty',
                'newPassword must be longer than or equal to 8 characters',
                'newPassword must not contain a NUL byte',
            ],
            $this->details($validator),
        );
    }

    public function testNoDeclaredFieldsGivesAnEmptyResult(): void
    {
        $this->assertSame([], (new Validator(['a' => 'b']))->validated());
    }

    public function testDeclaringAFieldTwiceIsALogicError(): void
    {
        $validator = new Validator([]);
        $validator->string('f');

        $this->expectException(LogicException::class);

        $validator->string('f');
    }

    /**
     * @return list<string>
     */
    private function details(Validator $validator): array
    {
        try {
            $validator->validated();
        } catch (ValidationFailedException $error) {
            return $error->details() ?? [];
        }

        $this->fail('Expected a ValidationFailedException');
    }
}
