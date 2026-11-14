<?php

declare(strict_types=1);

namespace Pidgey\Email;

use DateTimeImmutable;
use Pidgey\Http\TooManyRequestsException;
use Pidgey\Infra\Transaction;
use Pidgey\Infra\WallClock;
use Pidgey\Repository\SmtpTestLimitRepository;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Limits the test emails of an organization: one in flight (a lease) and a fixed window count.
 */
final class TestEmailLimit
{
    private const LEASE_REFUSED = 'A test email is already in progress';

    private const WINDOW_REFUSED = 'Too many test emails; try again later';

    /**
     * Builds the limit.
     *
     * @param SmtpTestLimitRepository $repository   The `smtp_test_limits` repository.
     * @param Transaction             $transaction  Runs the locked read-and-write.
     * @param WallClock               $clock        Source of the window and lease times.
     * @param LoggerInterface         $logger       Receives the warning of a failed release.
     * @param integer                 $maxAttempts  Test emails allowed per window.
     * @param integer                 $window       Window length, in seconds.
     * @param integer                 $leaseSeconds Lease length, in seconds.
     */
    public function __construct(
        private readonly SmtpTestLimitRepository $repository,
        private readonly Transaction $transaction,
        private readonly WallClock $clock,
        private readonly LoggerInterface $logger,
        private readonly int $maxAttempts,
        private readonly int $window,
        private readonly int $leaseSeconds,
    ) {
    }

    /**
     * Counts one attempt and takes the lease, or refuses.
     *
     * A refused attempt leaves the row unchanged. The lease is checked before the window.
     *
     * @param integer $organizationId Organization sending the test email.
     * @param integer $connectionId   Connection being tested.
     *
     * @return TestEmailLease
     *
     * @throws TooManyRequestsException When the lease is held or the window is full.
     */
    public function acquire(int $organizationId, int $connectionId): TestEmailLease
    {
        $now = $this->clock->now();

        return $this->transaction->run(function () use ($organizationId, $connectionId, $now): TestEmailLease {
            $limit = $this->repository->lockForOrganization($organizationId, $now);

            if ($limit->busyUntil !== null && $limit->busyUntil > $now) {
                throw new TooManyRequestsException(self::LEASE_REFUSED, self::retryAfter($now, $limit->busyUntil));
            }

            $windowStartedAt = $limit->windowStartedAt;
            $attempts = $limit->attempts;
            $windowEnd = $windowStartedAt->modify("+{$this->window} seconds");

            if ($windowEnd <= $now) {
                $windowStartedAt = $now;
                $attempts = 0;
                $windowEnd = $now->modify("+{$this->window} seconds");
            }

            if ($attempts >= $this->maxAttempts) {
                throw new TooManyRequestsException(self::WINDOW_REFUSED, self::retryAfter($now, $windowEnd));
            }

            $busyUntil = $now->modify("+{$this->leaseSeconds} seconds");
            $this->repository->takeAttempt($organizationId, $windowStartedAt, $attempts + 1, $busyUntil);

            return new TestEmailLease($organizationId, $connectionId, $busyUntil);
        });
    }

    /**
     * Releases the lease; a failure is logged and never thrown, so it cannot change the response.
     *
     * @param TestEmailLease $lease The lease returned by acquire().
     *
     * @return void
     */
    public function release(TestEmailLease $lease): void
    {
        try {
            $this->repository->releaseLease($lease->organizationId, $lease->busyUntil);
        } catch (Throwable $error) {
            $this->logger->warning('smtp-test-lease-release-failed', [
                'organizationId' => $lease->organizationId,
                'connectionId' => $lease->connectionId,
                'exception' => $error::class,
            ]);
        }
    }

    /**
     * Whole seconds from now to a later instant, rounded up, at least 1.
     *
     * @param DateTimeImmutable $now   The current time.
     * @param DateTimeImmutable $until The instant the refusal ends.
     *
     * @return integer
     */
    private static function retryAfter(DateTimeImmutable $now, DateTimeImmutable $until): int
    {
        $seconds = (float) $until->format('U.u') - (float) $now->format('U.u');

        return max(1, (int) ceil($seconds));
    }
}
