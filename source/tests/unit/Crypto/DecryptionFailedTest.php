<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Crypto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Crypto\DecryptionFailed;
use RuntimeException;

#[CoversClass(DecryptionFailed::class)]
final class DecryptionFailedTest extends TestCase
{
    public function testHasAFixedMessageAndTheKeyIdTried(): void
    {
        $failure = new DecryptionFailed('0011223344556677');

        $this->assertInstanceOf(RuntimeException::class, $failure);
        $this->assertSame('The stored SMTP password could not be decrypted', $failure->getMessage());
        $this->assertSame('0011223344556677', $failure->keyId);
    }

    public function testKeyIdIsNullWhenNoneWasRead(): void
    {
        $failure = new DecryptionFailed();

        $this->assertSame(DecryptionFailed::MESSAGE, $failure->getMessage());
        $this->assertNull($failure->keyId);
    }
}
