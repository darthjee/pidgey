<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pidgey\Auth\ApiTokenValue;
use Pidgey\Email\IdempotencyKeys;
use Pidgey\Email\Message;
use Pidgey\Http\Handler\SendMessageHandler;
use Pidgey\Smtp\HostPolicy;
use Pidgey\Smtp\HostResolver;
use Pidgey\Tests\Support\SendMessageEndpointTestCase;
use RuntimeException;

/**
 * `POST /v1/messages` with an `Idempotency-Key` (see `api/endpoints/sending.md#idempotency-key`).
 */
#[CoversClass(SendMessageHandler::class)]
final class SendMessageIdempotencyTest extends SendMessageEndpointTestCase
{
    private const KEY = 'key-0001';

    private const MESSAGE = ['to' => 'user@example.com', 'subject' => 'Hello', 'text' => 'Hi!'];

    public function testWithoutTheHeaderNoRowIsWrittenOnAnyAnswer(): void
    {
        $account = $this->account();
        $noConnection = $this->account(connected: false);

        $sent = $this->post($account['token']);
        $failed = $this->post($noConnection['token']);

        $this->assertSame(200, $sent->status());
        $this->assertSame(409, $failed->status());
        $this->assertSame([], $this->keyRows());
    }

    public function testTheAnswerIsStoredAndReplayedWithoutSendingAgain(): void
    {
        $account = $this->account();

        $first = $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);
        $second = $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);

        $this->assertSame(200, $first->status(), $first->body());
        $this->assertNull(self::replayed($first));
        $this->assertSame(200, $second->status());
        $this->assertSame($first->body(), $second->body());
        $this->assertSame('true', self::replayed($second));
        $this->assertContains('Content-Type: application/json', $second->headers());
        $this->assertSame(1, $this->sentCount());
        $this->assertCount(1, $this->attempts());
        $rows = $this->keyRows();
        $this->assertCount(1, $rows);
        $this->assertSame('completed', $rows[0]['state']);
        $this->assertSame('200', (string) $rows[0]['response_status']);
        $this->assertSame($first->body(), $rows[0]['response_body']);
    }

    public function testTheStoredRowKeepsOnlyHashes(): void
    {
        $account = $this->account();

        $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);

        $row = $this->keyRows()[0];
        $this->assertSame(IdempotencyKeys::keyHash(self::KEY), $row['key_hash']);
        $this->assertSame(IdempotencyKeys::requestHash($this->message()), $row['request_hash']);
        $this->assertStringNotContainsString(self::KEY, implode('|', array_map('strval', $row)));
    }

    public function testTheSameKeyWithAnotherPayloadAnswers422(): void
    {
        $account = $this->account();
        $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);

        $response = $this->post(
            $account['token'],
            ['to' => 'other@example.com', 'subject' => 'Hello', 'text' => 'Hi!'],
            ['Idempotency-Key' => self::KEY],
        );

        $this->assertError(
            $response,
            422,
            'IDEMPOTENCY_KEY_REUSED',
            'Idempotency-Key was already used with a different payload',
        );
        $this->assertSame(1, $this->sentCount());
    }

    public function testAnInFlightKeyWithinTheLeaseAnswers409(): void
    {
        $account = $this->account();
        $this->seed($account['organizationId'], 'in_flight', leaseOffset: 200);

        $response = $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);

        $this->assertError(
            $response,
            409,
            'IDEMPOTENCY_KEY_IN_USE',
            'A request with this Idempotency-Key is still in progress',
        );
        $this->assertSame(0, $this->sentCount());
    }

    public function testAnInFlightKeyPastTheLeaseIsCompletedAsAStored504AndReplayed(): void
    {
        $account = $this->account();
        $this->seed($account['organizationId'], 'in_flight', leaseOffset: -10);

        $first = $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);
        $second = $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);

        $this->assertError($first, 504, 'SMTP_TIMEOUT', 'The SMTP server timed out');
        $this->assertSame('true', self::replayed($first));
        $this->assertSame($first->body(), $second->body());
        $this->assertSame(0, $this->sentCount());
        $this->assertSame('completed', $this->keyRows()[0]['state']);
    }

    public function testATemporaryFailureReleasesTheKeySoTheRetrySends(): void
    {
        $account = $this->account(connected: false);
        $this->scriptedConnection($account['organizationId'], [['reply' => "421 4.3.2 busy\r\n"]]);

        $first = $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);

        $this->assertError($first, 503, 'SMTP_TEMPORARY_FAILURE');
        $this->assertSame([], $this->keyRows());

        $this->mailpitConnection($account['organizationId']);
        $second = $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);

        $this->assertSame(200, $second->status(), $second->body());
        $this->assertNull(self::replayed($second));
        $this->assertSame(1, $this->sentCount());
    }

    /**
     * @return array<string, array{list<array<string, mixed>>, int, string}>
     */
    public static function storedFailures(): array
    {
        return [
            'timeout' => [[['delayMs' => 3000, 'reply' => "220 late\r\n"]], 504, 'SMTP_TIMEOUT'],
            'protocol error' => [self::untilRecipient("250-ok\r\n251 mixed\r\n"), 502, 'SMTP_PROTOCOL_ERROR'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $steps
     */
    #[DataProvider('storedFailures')]
    public function testDeliveryUnknownFailuresAreStoredAndReplayed(array $steps, int $status, string $code): void
    {
        $account = $this->account(connected: false);
        $this->scriptedConnection($account['organizationId'], $steps);

        $first = $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);
        $second = $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);

        $this->assertError($first, $status, $code);
        $this->assertNull(self::replayed($first));
        $this->assertSame($status, $second->status());
        $this->assertSame($first->body(), $second->body());
        $this->assertSame('true', self::replayed($second));
        $this->assertSame('completed', $this->keyRows()[0]['state']);
        $this->assertCount(1, $this->attempts());
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function releasedFailures(): array
    {
        return [
            'rejected' => [502, 'SMTP_REJECTED'],
            'host not allowed' => [409, 'SMTP_HOST_NOT_ALLOWED'],
        ];
    }

    #[DataProvider('releasedFailures')]
    public function testOtherFailuresReleaseTheKey(int $status, string $code): void
    {
        $account = $this->account(connected: false);

        if ($code === 'SMTP_REJECTED') {
            $this->scriptedConnection($account['organizationId'], self::untilRecipient("550 5.1.1 Unknown\r\n"));
        } else {
            $this->mailpitConnection($account['organizationId']);
            putenv('SMTP_ALLOW_PRIVATE_HOSTS=false');
        }

        $response = $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);

        $this->assertError($response, $status, $code);
        $this->assertSame([], $this->keyRows());
    }

    public function testAnUnexpectedErrorAfterTheSendStartedIsStoredAs500(): void
    {
        $account = $this->account();
        $resolver = $this->createStub(HostResolver::class);
        $resolver->method('resolve')->willThrowException(new RuntimeException('resolver exploded'));
        $headers = ['Authorization' => 'Bearer ' . $account['token'], 'Idempotency-Key' => self::KEY];

        $first = $this->request('POST', $headers, hostPolicy: new HostPolicy($resolver, true));
        $second = $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);

        $this->assertError($first, 500, 'INTERNAL_ERROR');
        $this->assertSame($first->body(), $this->keyRows()[0]['response_body']);
        $this->assertSame($first->body(), $second->body());
        $this->assertSame('true', self::replayed($second));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidKeys(): array
    {
        return [
            'empty' => [''],
            '256 characters' => [str_repeat('k', 256)],
            'a space' => ['two words'],
            'a tab' => ["tab\tkey"],
            'a non-ASCII character' => ['chavé'],
            'a control character' => ["a\x01b"],
        ];
    }

    #[DataProvider('invalidKeys')]
    public function testAnInvalidKeyAnswers400AndTouchesNothing(string $key): void
    {
        $account = $this->account();

        $response = $this->post($account['token'], headers: ['Idempotency-Key' => $key]);

        $this->assertError(
            $response,
            400,
            'VALIDATION_FAILED',
            null,
            ['Idempotency-Key must be 1 to 255 printable ASCII characters'],
        );
        $this->assertSame([], $this->keyRows());
        $this->assertSame(0, $this->sentCount());
    }

    public function testAKeyOf255CharactersIsAccepted(): void
    {
        $account = $this->account();

        $response = $this->post($account['token'], headers: ['Idempotency-Key' => str_repeat('k', 255)]);

        $this->assertSame(200, $response->status(), $response->body());
    }

    public function testThePayloadIsValidatedBeforeTheKey(): void
    {
        $account = $this->account();

        $response = $this->post($account['token'], ['to' => 'nope'], ['Idempotency-Key' => '']);

        $this->assertSame(400, $response->status());
        $this->assertStringNotContainsString('Idempotency-Key', $response->body());
    }

    public function testTheSameKeyInTwoOrganizationsSendsTwice(): void
    {
        $first = $this->account();
        $second = $this->account();

        $one = $this->post($first['token'], headers: ['Idempotency-Key' => self::KEY]);
        $two = $this->post($second['token'], headers: ['Idempotency-Key' => self::KEY]);

        $this->assertSame(200, $one->status());
        $this->assertSame(200, $two->status());
        $this->assertNull(self::replayed($two));
        $this->assertSame(2, $this->sentCount());
        $this->assertCount(2, $this->keyRows());
    }

    public function testTheSameKeyWithAnotherTokenOfTheOrganizationReplays(): void
    {
        $account = $this->account();
        $other = ApiTokenValue::generate();
        $this->fixtures->apiToken(
            organizationId: $account['organizationId'],
            tokenHash: ApiTokenValue::hash($other),
        );

        $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);
        $second = $this->post($other, headers: ['Idempotency-Key' => self::KEY]);

        $this->assertSame('true', self::replayed($second));
        $this->assertSame(1, $this->sentCount());
    }

    public function testAnExpiredKeySendsAgain(): void
    {
        $account = $this->account();
        $this->seed($account['organizationId'], 'completed', expiresOffset: -60);

        $response = $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);

        $this->assertSame(200, $response->status(), $response->body());
        $this->assertNull(self::replayed($response));
        $this->assertSame(1, $this->sentCount());
        $this->assertSame('completed', $this->keyRows()[0]['state']);
        $this->assertSame('200', (string) $this->keyRows()[0]['response_status']);
    }

    public function testTheLazyCleanupDeletesAtMostOneBatchOfExpiredRows(): void
    {
        $account = $this->account();
        $other = $this->account();
        $total = IdempotencyKeys::CLEANUP_BATCH + 5;

        for ($index = 0; $index < $total; $index++) {
            $this->seed($account['organizationId'], 'completed', expiresOffset: -60, key: "old-{$index}");
        }

        $this->seed($other['organizationId'], 'completed', expiresOffset: -60, key: 'old-other');

        $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);

        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM idempotency_keys WHERE organization_id = :id');
        $statement->execute(['id' => $account['organizationId']]);
        $this->assertSame($total - IdempotencyKeys::CLEANUP_BATCH + 1, (int) $statement->fetchColumn());
        $statement->execute(['id' => $other['organizationId']]);
        $this->assertSame(1, (int) $statement->fetchColumn());
    }

    public function testNoActiveConnectionAnswers409StoresNothingAndTheKeyWorksOnceConnected(): void
    {
        $account = $this->account(connected: false);

        $first = $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);

        $this->assertError($first, 409, 'NO_SMTP_CONNECTION');
        $this->assertSame([], $this->keyRows());

        $this->mailpitConnection($account['organizationId']);
        $second = $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);

        $this->assertSame(200, $second->status(), $second->body());
        $this->assertNull(self::replayed($second));
    }

    public function testAReplayStillAnswersAfterTheActiveConnectionWasDeleted(): void
    {
        $account = $this->account();
        $first = $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);
        $this->pdo->exec('DELETE FROM smtp_connections');

        $second = $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);

        $this->assertSame(200, $second->status());
        $this->assertSame($first->body(), $second->body());
        $this->assertSame('true', self::replayed($second));
    }

    public function testAnAuthenticationFailureNeverTouchesTheStoredKeys(): void
    {
        $account = $this->account(scopes: []);

        $unauthorized = $this->post(null, headers: ['Idempotency-Key' => self::KEY]);
        $forbidden = $this->post($account['token'], headers: ['Idempotency-Key' => self::KEY]);

        $this->assertSame(401, $unauthorized->status());
        $this->assertSame(403, $forbidden->status());
        $this->assertSame([], $this->keyRows());
    }

    private function message(): Message
    {
        return new Message(self::MESSAGE['to'], self::MESSAGE['subject'], self::MESSAGE['text'], null);
    }

    /**
     * Inserts a row of the key for the default message.
     */
    private function seed(
        int $organizationId,
        string $state,
        int $leaseOffset = 300,
        int $expiresOffset = 3600,
        string $key = self::KEY,
    ): void {
        $completed = $state === 'completed';
        $this->fixtures->insert('idempotency_keys', [
            'organization_id' => $organizationId,
            'key_hash' => IdempotencyKeys::keyHash($key),
            'request_hash' => IdempotencyKeys::requestHash($this->message()),
            'state' => $state,
            'response_status' => $completed ? 200 : null,
            'response_body' => $completed ? '{"status":"sent","smtp":{"code":250,"message":"old"}}' : null,
            'lease_until' => gmdate('Y-m-d H:i:s', time() + $leaseOffset),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + $expiresOffset),
        ]);
    }
}
