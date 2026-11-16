<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pidgey\Http\Handler\SmtpConnectionTestHandler;
use Pidgey\Model\UserStatus;
use Pidgey\Tests\Support\SmtpConnectionTestEndpointTestCase;

/**
 * `POST /smtp-connections/:id/test.json` through the real routes, delivering to Mailpit.
 */
#[CoversClass(SmtpConnectionTestHandler::class)]
final class SmtpConnectionTestRecipientTest extends SmtpConnectionTestEndpointTestCase
{
    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidRecipients(): array
    {
        return [
            'empty' => ['', 'to should not be empty'],
            'whitespace' => ['   ', 'to should not be empty'],
            'not a string' => [12, 'to must be a string'],
            'bad format' => ['not-an-email', 'to must be an email'],
            'too long' => [str_repeat('a', 250) . '@example.com', 'to must be shorter than or equal to 255 characters'],
            'line break' => ["bob@example.com\r\nBcc: x@example.com", 'to must not contain line breaks'],
        ];
    }

    #[DataProvider('invalidRecipients')]
    public function testAnInvalidToIsRefusedBeforeTheLookupAndTheLimit(mixed $to, string $message): void
    {
        $user = $this->member('caller@example.com');

        $response = $this->send($user, 'POST', self::path($this->plainConnection($user) + 999), ['to' => $to]);

        $this->assertSame(400, $response->status(), $response->body());
        $this->assertContains($message, self::json($response)['error']['details']);
        $this->assertNoAttempt($user);
    }

    public function testAnAddressThatIsNotAnApprovedOrganizationUserIsRefusedAlike(): void
    {
        $user = $this->member('caller@example.com');
        $id = $this->plainConnection($user);
        $this->member('pending@example.com', $user->organizationId, UserStatus::Pending);
        $this->member('denied@example.com', $user->organizationId, UserStatus::Denied);
        $this->member('foreign@example.com');

        foreach (['pending@example.com', 'denied@example.com', 'foreign@example.com', 'nobody@example.com'] as $to) {
            $response = $this->send($user, 'POST', self::path($id), ['to' => $to]);

            $this->assertValidationFailed($response, [self::NOT_A_USER]);
        }

        $this->assertNoAttempt($user);
        $this->assertSame([], $this->mailpit->messages());
        $this->assertSame([], $this->operatorLines());
    }

    public function testAnUnknownOrForeignConnectionIsNotFound(): void
    {
        $user = $this->member('caller@example.com');
        $foreign = $this->fixtures->smtpConnection(organizationId: $this->account()->organizationId, port: 2025);

        foreach ([$foreign, $foreign + 1000] as $id) {
            $response = $this->send($user, 'POST', self::path($id));

            $this->assertError($response, 404, 'NOT_FOUND', self::NOT_FOUND);
        }

        $this->assertNoAttempt($user);
        $this->assertSame([], $this->operatorLines());
    }
}
