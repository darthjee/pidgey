<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use LogicException;
use Pidgey\Email\SendOutcome;

trait SendFlowAssertions
{
    protected function assertSendTimeFailure(string $code, SendOutcome $outcome): void
    {
        $this->assertFalse($outcome->isSent());
        $this->assertSame($code, $outcome->failureCode());
        $this->assertSame('send-time-check', $outcome->step());
        $this->assertNull($outcome->replyCode());
        $this->assertNull($outcome->replyMessage());
        $this->assertNull($outcome->tlsReason());
        $this->assertSame([], $this->calls, 'the transport is never called');
    }

    protected function assertWiped(): void
    {
        $credentials = $this->calls[0]['credentials'];
        $this->assertNotNull($credentials);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('SecretString: wiped');

        $credentials->password()->reveal();
    }
}
