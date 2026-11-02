<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\RefreshTokenValue;

#[CoversClass(RefreshTokenValue::class)]
final class RefreshTokenValueTest extends TestCase
{
    public function testGeneratesNinetySixLowercaseHexCharacters(): void
    {
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{96}\z/', RefreshTokenValue::generate());
    }

    public function testGeneratesDistinctValues(): void
    {
        $this->assertNotSame(RefreshTokenValue::generate(), RefreshTokenValue::generate());
    }

    public function testHashIsTheSha256HexDigest(): void
    {
        $this->assertSame(
            'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad',
            RefreshTokenValue::hash('abc'),
        );
    }
}
