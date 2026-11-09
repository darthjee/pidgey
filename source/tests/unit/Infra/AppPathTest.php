<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\AppPath;

#[CoversClass(AppPath::class)]
final class AppPathTest extends TestCase
{
    public function testRootIsTheSourceDirectory(): void
    {
        $this->assertSame(realpath(__DIR__ . '/../../..'), realpath(AppPath::root()));
        $this->assertFileExists(AppPath::root() . '/composer.json');
        $this->assertStringStartsWith('/', AppPath::root());
    }

    public function testAbsolutePathIsUnchanged(): void
    {
        $this->assertSame('/etc/ssl/ca.pem', AppPath::resolve('/etc/ssl/ca.pem'));
    }

    public function testRelativePathIsJoinedToTheRoot(): void
    {
        $expected = AppPath::root() . '/tests/fixtures/tls/ca.pem';

        $this->assertSame($expected, AppPath::resolve('tests/fixtures/tls/ca.pem'));
    }
}
