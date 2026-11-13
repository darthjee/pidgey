<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Email;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Email\Message;

#[CoversClass(Message::class)]
final class MessageTest extends TestCase
{
    public function testKeepsItsFields(): void
    {
        $message = new Message('to@example.com', 'Hello', 'text', '<p>html</p>');

        $this->assertSame('to@example.com', $message->to);
        $this->assertSame('Hello', $message->subject);
        $this->assertSame('text', $message->text);
        $this->assertSame('<p>html</p>', $message->html);
    }

    public function testAcceptsATextOnlyMessage(): void
    {
        $message = new Message('to@example.com', 'Hello', 'text', null);

        $this->assertNull($message->html);
    }

    public function testAcceptsAnHtmlOnlyMessage(): void
    {
        $message = new Message('to@example.com', 'Hello', null, '<p>html</p>');

        $this->assertNull($message->text);
    }

    public function testRefusesAMessageWithoutBody(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Message: a text or html body is required');

        new Message('to@example.com', 'Hello', null, null);
    }
}
