<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use Pidgey\Http\Handler\SmtpConnectionTestHandler;
use Pidgey\Tests\Support\SmtpConnectionTestEndpointTestCase;

/**
 * `POST /smtp-connections/:id/test.json` through the real routes, delivering to Mailpit.
 */
#[CoversClass(SmtpConnectionTestHandler::class)]
final class SmtpConnectionTestLimitTest extends SmtpConnectionTestEndpointTestCase
{
    public function testTheWindowRefusesWithRetryAfterAndDoesNotCountTheRefusal(): void
    {
        $user = $this->member('caller@example.com');
        $id = $this->plainConnection($user);

        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $this->assertSame(201, $this->send($user, 'POST', self::path($id))->status());
        }

        $response = $this->send($user, 'POST', self::path($id));

        $this->assertError($response, 429, 'TOO_MANY_REQUESTS', 'Too many test emails; try again later');
        $retry = $this->retryAfter($response);
        // Slack for the time the sends took: the window started at the first send.
        $this->assertGreaterThanOrEqual(3500, $retry);
        $this->assertLessThanOrEqual(3600, $retry);
        $this->assertSame(self::MAX_ATTEMPTS, (int) $this->limitRow($user)['attempts']);
        $this->assertCount(self::MAX_ATTEMPTS, $this->mailpit->messages());
        $this->assertCount(self::MAX_ATTEMPTS, $this->testLines());
    }

    public function testAHeldLeaseRefusesFirst(): void
    {
        $user = $this->member('caller@example.com');
        $id = $this->plainConnection($user);
        $this->fixtures->insert('smtp_test_limits', [
            'organization_id' => $user->organizationId,
            'window_started_at' => gmdate('Y-m-d H:i:s'),
            'attempts' => self::MAX_ATTEMPTS,
            'busy_until' => gmdate('Y-m-d H:i:s', time() + 20),
        ]);

        $response = $this->send($user, 'POST', self::path($id));

        $this->assertError($response, 429, 'TOO_MANY_REQUESTS', 'A test email is already in progress');
        $this->assertGreaterThanOrEqual(1, $this->retryAfter($response));
        $this->assertLessThanOrEqual(20, $this->retryAfter($response));
        $this->assertSame([], $this->mailpit->messages());
        $this->assertSame([], $this->operatorLines());
    }

    public function testAnExpiredLeaseIsIgnored(): void
    {
        $user = $this->member('caller@example.com');
        $id = $this->plainConnection($user);
        $this->fixtures->insert('smtp_test_limits', [
            'organization_id' => $user->organizationId,
            'window_started_at' => gmdate('Y-m-d H:i:s'),
            'attempts' => 0,
            'busy_until' => gmdate('Y-m-d H:i:s', time() - 5),
        ]);

        $this->assertSame(201, $this->send($user, 'POST', self::path($id))->status());
    }

    public function testTheLimitIsPerOrganization(): void
    {
        $user = $this->member('caller@example.com');
        $other = $this->member('other@example.com');
        $id = $this->plainConnection($user);
        $otherId = $this->plainConnection($other);

        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $this->send($user, 'POST', self::path($id));
        }

        $this->assertSame(429, $this->send($user, 'POST', self::path($id))->status());
        $this->assertSame(201, $this->send($other, 'POST', self::path($otherId))->status());
    }
}
