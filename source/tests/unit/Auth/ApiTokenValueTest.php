<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\ApiTokenValue;

#[CoversClass(ApiTokenValue::class)]
final class ApiTokenValueTest extends TestCase
{
    public function testPrefixIsPdg(): void
    {
        $this->assertSame('pdg_', ApiTokenValue::PREFIX);
    }

    public function testGeneratesPrefixedHexToken(): void
    {
        $this->assertMatchesRegularExpression('/\Apdg_[0-9a-f]{64}\z/', ApiTokenValue::generate());
    }

    public function testGeneratesDistinctValues(): void
    {
        $this->assertNotSame(ApiTokenValue::generate(), ApiTokenValue::generate());
    }

    public function testHashIsTheSha256HexDigest(): void
    {
        $hash = ApiTokenValue::hash('pdg_abc');

        $this->assertSame(hash('sha256', 'pdg_abc'), $hash);
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $hash);
    }
}
