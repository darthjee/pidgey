<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Email;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Email\ReplyText;
use Pidgey\Infra\SecretString;

#[CoversClass(ReplyText::class)]
#[UsesClass(SecretString::class)]
final class ReplyTextTest extends TestCase
{
    private const PASSWORD = 'canary-Pa55word';
    private const USERNAME = 'mailer@example.com';

    public function testKeepsAPlainReply(): void
    {
        $this->assertSame('2.0.0 Ok: queued as 1234', ReplyText::clean('2.0.0 Ok: queued as 1234', null, null));
    }

    public function testRedactsThePassword(): void
    {
        $text = ReplyText::clean('5.7.8 bad password ' . self::PASSWORD . ' here', $this->password(), self::USERNAME);

        $this->assertSame('5.7.8 bad password [redacted] here', $text);
    }

    public function testRedactsTheBase64Password(): void
    {
        $text = ReplyText::clean('rejected ' . base64_encode(self::PASSWORD), $this->password(), self::USERNAME);

        $this->assertSame('rejected [redacted]', $text);
    }

    public function testRedactsTheBase64AuthPlainPayload(): void
    {
        $payload = base64_encode("\0" . self::USERNAME . "\0" . self::PASSWORD);

        $text = ReplyText::clean("5.7.8 {$payload} invalid", $this->password(), self::USERNAME);

        $this->assertSame('5.7.8 [redacted] invalid', $text);
    }

    public function testRedactsEveryOccurrence(): void
    {
        $text = ReplyText::clean(self::PASSWORD . ' and ' . self::PASSWORD, $this->password(), null);

        $this->assertSame('[redacted] and [redacted]', $text);
    }

    public function testRedactsWithoutAUsername(): void
    {
        $text = ReplyText::clean('x ' . base64_encode(self::PASSWORD), $this->password(), null);

        $this->assertSame('x [redacted]', $text);
    }

    public function testRedactsBeforeSanitizing(): void
    {
        $password = new SecretString("p\u{e4}ss\tword");

        $this->assertSame('echo [redacted]', ReplyText::clean("echo p\u{e4}ss\tword", $password, null));
    }

    public function testLeavesTheTextWhenThePasswordIsEmpty(): void
    {
        $this->assertSame('ok', ReplyText::clean('ok', new SecretString(''), self::USERNAME));
    }

    public function testReplacesNonAsciiAndControlBytes(): void
    {
        $this->assertSame('a??b?c?d', ReplyText::clean("a\u{e9}b\0c\x7Fd", null, null));
    }

    public function testCollapsesTabsAndSpaceRunsAndTrims(): void
    {
        $this->assertSame('a b c', ReplyText::clean("  a\t\t b   c \t", null, null));
    }

    public function testKeepsExactly512Characters(): void
    {
        $text = str_repeat('a', 512);

        $this->assertSame($text, ReplyText::clean($text, null, null));
    }

    public function testCutsALongerTextTo509PlusEllipsis(): void
    {
        $cleaned = ReplyText::clean(str_repeat('a', 513), null, null);

        $this->assertSame(str_repeat('a', 509) . '...', $cleaned);
        $this->assertSame(512, strlen($cleaned));
    }

    public function testCapsAfterCollapsing(): void
    {
        $this->assertSame(str_repeat('a ', 255) . 'a', ReplyText::clean(str_repeat('a   ', 255) . 'a', null, null));
    }

    private function password(): SecretString
    {
        return new SecretString(self::PASSWORD);
    }
}
