<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\SessionUuid;

#[CoversClass(SessionUuid::class)]
final class SessionUuidTest extends TestCase
{
    private const UUID_V4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public function testGeneratesAVersionFourUuid(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->assertMatchesRegularExpression(self::UUID_V4, SessionUuid::generate());
        }
    }

    public function testGeneratesDistinctValues(): void
    {
        $this->assertNotSame(SessionUuid::generate(), SessionUuid::generate());
    }
}
