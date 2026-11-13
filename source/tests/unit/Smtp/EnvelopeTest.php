<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Smtp;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Smtp\Envelope;

#[CoversClass(Envelope::class)]
final class EnvelopeTest extends TestCase
{
    public function testKeepsTheSenderAndTheRecipients(): void
    {
        $envelope = new Envelope('from@example.com', ['to@example.com']);

        $this->assertSame('from@example.com', $envelope->from);
        $this->assertSame(['to@example.com'], $envelope->recipients);
    }

    public function testRecipientsAreReindexedAsAList(): void
    {
        $envelope = new Envelope('from@example.com', [3 => 'a@example.com', 7 => 'b@example.com']);

        $this->assertSame(['a@example.com', 'b@example.com'], $envelope->recipients);
    }

    public function testRefusesAnEmptyRecipientList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Envelope: at least one recipient is required');

        new Envelope('from@example.com', []);
    }
}
