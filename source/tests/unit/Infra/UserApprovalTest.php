<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\Env;
use Pidgey\Infra\UserApproval;

#[CoversClass(UserApproval::class)]
#[UsesClass(Env::class)]
final class UserApprovalTest extends TestCase
{
    private string|false $saved = false;

    protected function setUp(): void
    {
        $this->saved = getenv(UserApproval::VARIABLE);
        putenv(UserApproval::VARIABLE);
    }

    protected function tearDown(): void
    {
        putenv($this->saved === false ? UserApproval::VARIABLE : UserApproval::VARIABLE . "={$this->saved}");
    }

    public function testExactFalseDisablesApproval(): void
    {
        putenv(UserApproval::VARIABLE . '=false');

        $this->assertFalse(UserApproval::required());
    }

    public function testUnsetRequiresApproval(): void
    {
        $this->assertTrue(UserApproval::required());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function otherValues(): array
    {
        return [
            'true' => ['true'],
            'empty' => [''],
            'zero' => ['0'],
            'upper case' => ['FALSE'],
            'capitalized' => ['False'],
            'no' => ['no'],
            'off' => ['off'],
            'surrounding space' => [' false '],
            'typo' => ['flase'],
        ];
    }

    #[DataProvider('otherValues')]
    public function testAnyOtherValueRequiresApproval(string $value): void
    {
        putenv(UserApproval::VARIABLE . "={$value}");

        $this->assertTrue(UserApproval::required());
    }
}
