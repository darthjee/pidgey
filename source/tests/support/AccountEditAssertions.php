<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Pidgey\Http\HttpException;
use Pidgey\Model\User;

/**
 * Assertions shared by the account edit tests.
 */
trait AccountEditAssertions
{
    protected function assertUnchanged(User $user): void
    {
        $stored = $this->reload($user);
        $this->assertSame($user->username, $stored->username);
        $this->assertSame($user->email, $stored->email);
        $this->assertSame($user->passwordDigest, $stored->passwordDigest);
    }

    protected function assertLockout(int $userId, int $failedAttempts, ?string $lockedUntil): void
    {
        $row = $this->lockoutRow($userId) ?? self::fail('The lockout row is missing.');
        $this->assertSame((string) $failedAttempts, (string) $row['failed_attempts']);
        $this->assertSame($lockedUntil, $row['locked_until']);
    }

    /**
     * @param callable(): mixed $call
     */
    protected function assertError(callable $call, int $status, ?string $code, string $message): void
    {
        try {
            $call();
            $this->fail('Expected an HttpException');
        } catch (HttpException $error) {
            $this->assertSame($status, $error->status());
            $this->assertSame($code, $error->errorCode());
            $this->assertSame($message, $error->errorMessage());
        }
    }
}
