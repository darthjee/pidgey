<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Email;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Email\Message;
use Pidgey\Email\TestEmail;

#[CoversClass(TestEmail::class)]
#[UsesClass(Message::class)]
final class TestEmailTest extends TestCase
{
    public function testBuildsTheFixedMessage(): void
    {
        $message = TestEmail::message('user@example.com');

        $this->assertSame('user@example.com', $message->to);
        $this->assertSame('Pidgey test email', $message->subject);
        $this->assertSame('This is a test email sent by Pidgey to check an SMTP connection.', $message->text);
        $this->assertNull($message->html);
    }
}
