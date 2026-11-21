<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Email;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Email\IdempotencyKeys;
use Pidgey\Email\IdempotencyReplay;
use Pidgey\Email\IdempotencyReservation;
use Pidgey\Email\Message;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\HttpException;
use Pidgey\Http\IdempotencyConflictException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\Response;
use Pidgey\Http\Timestamp;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Model\IdempotencyKey;
use Pidgey\Model\Row;
use Pidgey\Repository\IdempotencyKeyRepository;
use Pidgey\Repository\Sql;
use Pidgey\Tests\Support\Fixtures;
use Pidgey\Tests\Support\FrozenWallClock;
use Pidgey\Tests\Support\RepositoryTestCase;
use Pidgey\Tests\Support\StatementHookPdo;

#[CoversClass(IdempotencyKeys::class)]
#[CoversClass(IdempotencyReplay::class)]
#[CoversClass(IdempotencyReservation::class)]
#[CoversClass(IdempotencyConflictException::class)]
#[UsesClass(Message::class)]
#[UsesClass(ValidationFailedException::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(Response::class)]
#[UsesClass(Timestamp::class)]
#[UsesClass(IdempotencyKeyRepository::class)]
#[UsesClass(IdempotencyKey::class)]
#[UsesClass(Row::class)]
#[UsesClass(Sql::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
final class IdempotencyKeysTest extends RepositoryTestCase
{
    private const START = 1_800_000_000;

    private const KEY = 'key-1';

    private const OK_BODY = '{"status":"sent","smtp":{"code":250,"message":"OK"}}';

    private FrozenWallClock $clock;

    private IdempotencyKeyRepository $repository;

    private IdempotencyKeys $keys;

    private int $organizationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = new FrozenWallClock(self::START);
        $this->repository = new IdempotencyKeyRepository($this->pdo);
        $this->keys = new IdempotencyKeys($this->repository, $this->clock);
        $this->organizationId = $this->fixtures->organization();
    }

    /**
     * @return array<string, array{string, boolean}>
     */
    public static function keyFormats(): array
    {
        return [
            'uuid' => ['3f2504e0-4f89-41d3-9a0c-0305e82c3301', true],
            'one character' => ['a', true],
            '255 characters' => [str_repeat('k', 255), true],
            'first and last printable' => ['!~', true],
            'empty' => ['', false],
            '256 characters' => [str_repeat('k', 256), false],
            'space' => ['a b', false],
            'tab' => ["a\tb", false],
            'newline' => ["key\n", false],
            'control' => ["a\x00b", false],
            'del' => ["a\x7Fb", false],
            'non-ascii' => ['chave-ção', false],
        ];
    }

    #[DataProvider('keyFormats')]
    public function testIsValidChecksTheFormat(string $key, bool $valid): void
    {
        $this->assertSame($valid, IdempotencyKeys::isValid($key));
    }

    public function testValidateAcceptsAValidKey(): void
    {
        IdempotencyKeys::validate(self::KEY);

        $this->addToAssertionCount(1);
    }

    public function testValidateRefusesAnInvalidKey(): void
    {
        try {
            IdempotencyKeys::validate('');
            $this->fail('Expected a ValidationFailedException');
        } catch (ValidationFailedException $exception) {
            $this->assertSame(400, $exception->status());
            $this->assertSame('VALIDATION_FAILED', $exception->errorCode());
            $this->assertSame(
                ['Idempotency-Key must be 1 to 255 printable ASCII characters'],
                $exception->details(),
            );
        }
    }

    public function testKeyHashIsTheSha256OfTheRawKey(): void
    {
        $this->assertSame(hash('sha256', 'abc'), IdempotencyKeys::keyHash('abc'));
    }

    public function testRequestHashHashesTheFieldsInOrderWithAbsentFieldsAsNull(): void
    {
        $message = new Message('to@example.com', 'Subject', 'Body', null);

        $this->assertSame(
            hash('sha256', '{"to":"to@example.com","subject":"Subject","text":"Body","html":null}'),
            IdempotencyKeys::requestHash($message),
        );
    }

    public function testRequestHashKeepsUnicodeAndSlashesUnescaped(): void
    {
        $message = new Message('to@example.com', 'Olá / ação', null, '<p>é</p>');

        $this->assertSame(
            hash('sha256', '{"to":"to@example.com","subject":"Olá / ação","text":null,"html":"<p>é</p>"}'),
            IdempotencyKeys::requestHash($message),
        );
    }

    public function testRequestHashDistinguishesAbsentFromEmptyAndTheFields(): void
    {
        $absent = IdempotencyKeys::requestHash(new Message('a@example.com', 's', 't', null));
        $empty = IdempotencyKeys::requestHash(new Message('a@example.com', 's', 't', ''));
        $swapped = IdempotencyKeys::requestHash(new Message('a@example.com', 's', null, 't'));

        $this->assertNotSame($absent, $empty);
        $this->assertNotSame($absent, $swapped);
    }

    public function testAnUnknownKeyIsReservedAsInFlightWithTheLeaseAndTheTtl(): void
    {
        $result = $this->keys->begin($this->organizationId, self::KEY, $this->message());

        $this->assertInstanceOf(IdempotencyReservation::class, $result);
        $this->assertSame($this->organizationId, $result->organizationId);
        $this->assertSame(IdempotencyKeys::keyHash(self::KEY), $result->keyHash);
        $row = $this->row(self::KEY);
        $this->assertSame('in_flight', $row['state']);
        $this->assertSame(IdempotencyKeys::requestHash($this->message()), $row['request_hash']);
        $this->assertSame($this->at(300), $row['lease_until']);
        $this->assertSame($this->at(86400), $row['expires_at']);
    }

    public function testTheRawKeyIsNeverStored(): void
    {
        $this->keys->begin($this->organizationId, self::KEY, $this->message());

        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM idempotency_keys WHERE key_hash = :key');
        $statement->execute(['key' => self::KEY]);

        $this->assertSame(0, (int) $statement->fetchColumn());
    }

    public function testACompletedKeyReplaysTheStoredAnswerVerbatim(): void
    {
        $this->complete(self::KEY, 200, self::OK_BODY);
        $this->clock->advance(3600);

        $result = $this->keys->begin($this->organizationId, self::KEY, $this->message());

        $this->assertInstanceOf(IdempotencyReplay::class, $result);
        $this->assertSame(200, $result->status);
        $this->assertSame(self::OK_BODY, $result->body);
    }

    public function testAStoredErrorEnvelopeIsReplayedWithItsOriginalTimestamp(): void
    {
        $body = '{"error":{"code":"SMTP_TIMEOUT","message":"x"},"statusCode":504,'
            . '"timestamp":"2020-01-01T00:00:00.000Z"}';
        $this->complete(self::KEY, 504, $body);

        $result = $this->keys->begin($this->organizationId, self::KEY, $this->message());

        $this->assertInstanceOf(IdempotencyReplay::class, $result);
        $this->assertSame(504, $result->status);
        $this->assertSame($body, $result->body);
    }

    public function testADifferentPayloadIsReusedWhetherCompletedOrInFlight(): void
    {
        $this->keys->begin($this->organizationId, 'in-flight', $this->message());
        $this->complete('completed', 200, self::OK_BODY);

        foreach (['in-flight', 'completed'] as $key) {
            $this->assertConflict(422, 'IDEMPOTENCY_KEY_REUSED', $key, $this->message('other subject'));
        }
    }

    public function testReusedHasTheFixedMessage(): void
    {
        $this->complete(self::KEY, 200, self::OK_BODY);

        try {
            $this->keys->begin($this->organizationId, self::KEY, $this->message('other'));
            $this->fail('Expected an IdempotencyConflictException');
        } catch (IdempotencyConflictException $exception) {
            $this->assertSame('Idempotency-Key was already used with a different payload', $exception->errorMessage());
        }
    }

    public function testAnInFlightKeyWithinTheLeaseIsInUse(): void
    {
        $this->keys->begin($this->organizationId, self::KEY, $this->message());
        $this->clock->advance(299);

        try {
            $this->keys->begin($this->organizationId, self::KEY, $this->message());
            $this->fail('Expected an IdempotencyConflictException');
        } catch (IdempotencyConflictException $exception) {
            $this->assertSame(409, $exception->status());
            $this->assertSame('IDEMPOTENCY_KEY_IN_USE', $exception->errorCode());
            $this->assertSame(
                'A request with this Idempotency-Key is still in progress',
                $exception->errorMessage(),
            );
        }
    }

    public function testAnInFlightKeyExactlyAtTheLeaseEndIsStale(): void
    {
        $this->keys->begin($this->organizationId, self::KEY, $this->message());
        $this->clock->advance(300);

        $result = $this->keys->begin($this->organizationId, self::KEY, $this->message());

        $this->assertInstanceOf(IdempotencyReplay::class, $result);
        $this->assertSame(504, $result->status);
    }

    public function testAStaleLeaseIsCompletedAsTheStoredTimeoutAndThenReplayed(): void
    {
        $this->keys->begin($this->organizationId, self::KEY, $this->message());
        $this->clock->advance(400);
        $expected = (new ErrorResponse(
            504,
            'SMTP_TIMEOUT',
            'The SMTP server timed out',
            null,
            null,
            $this->clock->now(),
        ))->body();

        $first = $this->keys->begin($this->organizationId, self::KEY, $this->message());
        $this->clock->advance(10);
        $second = $this->keys->begin($this->organizationId, self::KEY, $this->message());

        $this->assertEquals(new IdempotencyReplay(504, $expected), $first);
        $this->assertEquals($first, $second);
        $row = $this->row(self::KEY);
        $this->assertSame('completed', $row['state']);
        $this->assertSame(504, (int) $row['response_status']);
        $this->assertSame($expected, $row['response_body']);
    }

    public function testAnExpiredKeyIsReservedAgainWithTheNewPayload(): void
    {
        $this->complete(self::KEY, 200, self::OK_BODY);
        $this->clock->advance(86400);

        $result = $this->keys->begin($this->organizationId, self::KEY, $this->message('new subject'));

        $this->assertInstanceOf(IdempotencyReservation::class, $result);
        $row = $this->row(self::KEY);
        $this->assertSame('in_flight', $row['state']);
        $this->assertSame(IdempotencyKeys::requestHash($this->message('new subject')), $row['request_hash']);
        $this->assertNull($row['response_body']);
    }

    public function testAKeyOneSecondBeforeExpiryStillReplays(): void
    {
        $this->complete(self::KEY, 200, self::OK_BODY);
        $this->clock->advance(86399);

        $this->assertInstanceOf(
            IdempotencyReplay::class,
            $this->keys->begin($this->organizationId, self::KEY, $this->message()),
        );
    }

    public function testReservingDeletesAtMostOneBatchOfExpiredRowsOfTheOrganization(): void
    {
        $other = $this->fixtures->organization();
        $this->seedExpired($this->organizationId, 101);
        $this->seedExpired($other, 3);

        $this->keys->begin($this->organizationId, self::KEY, $this->message());

        $this->assertSame(1 + 1, $this->rowCount($this->organizationId));
        $this->assertSame(3, $this->rowCount($other));
    }

    public function testTheSameKeyOfAnotherOrganizationIsIndependent(): void
    {
        $other = $this->fixtures->organization();
        $this->complete(self::KEY, 200, self::OK_BODY);

        $result = $this->keys->begin($other, self::KEY, $this->message('another payload'));

        $this->assertInstanceOf(IdempotencyReservation::class, $result);
    }

    public function testLosingTheReservationRaceAppliesTheRulesToTheWinnersRow(): void
    {
        $hooked = $this->hookedKeys();
        $hooked['pdo']->beforeNext('INSERT INTO idempotency_keys', function (): void {
            $this->insertRow(self::KEY, $this->message(), 'in_flight', null, null);
        });

        $this->expectException(IdempotencyConflictException::class);
        $this->expectExceptionCode(409);

        $hooked['keys']->begin($this->organizationId, self::KEY, $this->message());
    }

    public function testLosingTheRaceToACompletedRowReplaysIt(): void
    {
        $hooked = $this->hookedKeys();
        $hooked['pdo']->beforeNext('INSERT INTO idempotency_keys', function (): void {
            $this->insertRow(self::KEY, $this->message(), 'completed', 200, self::OK_BODY);
        });

        $result = $hooked['keys']->begin($this->organizationId, self::KEY, $this->message());

        $this->assertEquals(new IdempotencyReplay(200, self::OK_BODY), $result);
    }

    public function testLosingTheRaceWithADifferentPayloadIsReused(): void
    {
        $hooked = $this->hookedKeys();
        $hooked['pdo']->beforeNext('INSERT INTO idempotency_keys', function (): void {
            $this->insertRow(self::KEY, $this->message('other'), 'in_flight', null, null);
        });

        $this->expectException(IdempotencyConflictException::class);
        $this->expectExceptionCode(422);

        $hooked['keys']->begin($this->organizationId, self::KEY, $this->message());
    }

    public function testACompletedRowWithoutAnAnswerIsAnError(): void
    {
        $this->insertRow(self::KEY, $this->message(), 'completed', null, null);

        $this->expectException(LogicException::class);

        $this->keys->begin($this->organizationId, self::KEY, $this->message());
    }

    /**
     * @return array<string, array{integer, string, boolean, boolean}>
     */
    public static function answers(): array
    {
        $error = static fn (int $status, string $code): string => (string) json_encode(
            ['error' => ['code' => $code, 'message' => 'x'], 'statusCode' => $status, 'timestamp' => 't'],
        );

        return [
            '200 sent' => [200, self::OK_BODY, false, true],
            '504 SMTP_TIMEOUT' => [504, $error(504, 'SMTP_TIMEOUT'), true, true],
            '502 SMTP_PROTOCOL_ERROR' => [502, $error(502, 'SMTP_PROTOCOL_ERROR'), true, true],
            '500 after the transaction started' => [500, $error(500, 'INTERNAL_ERROR'), true, true],
            '500 before the transaction' => [500, $error(500, 'INTERNAL_ERROR'), false, false],
            '409 NO_SMTP_CONNECTION' => [409, $error(409, 'NO_SMTP_CONNECTION'), false, false],
            '409 SMTP_INSECURE_CREDENTIALS' => [409, $error(409, 'SMTP_INSECURE_CREDENTIALS'), false, false],
            '409 SMTP_HOST_NOT_ALLOWED' => [409, $error(409, 'SMTP_HOST_NOT_ALLOWED'), false, false],
            '409 SMTP_PASSWORD_UNREADABLE' => [409, $error(409, 'SMTP_PASSWORD_UNREADABLE'), false, false],
            '502 SMTP_CONNECTION_FAILED' => [502, $error(502, 'SMTP_CONNECTION_FAILED'), true, false],
            '503 SMTP_TEMPORARY_FAILURE' => [503, $error(503, 'SMTP_TEMPORARY_FAILURE'), true, false],
            '502 SMTP_REJECTED' => [502, $error(502, 'SMTP_REJECTED'), true, false],
            '502 SMTP_AUTH_FAILED' => [502, $error(502, 'SMTP_AUTH_FAILED'), true, false],
            '502 SMTP_TLS_FAILED' => [502, $error(502, 'SMTP_TLS_FAILED'), true, false],
            '502 with a body that is not JSON' => [502, 'not json', true, false],
            '502 with an unexpected JSON shape' => [502, '[1]', true, false],
        ];
    }

    #[DataProvider('answers')]
    public function testFinishStoresOrReleasesByAnswer(int $status, string $body, bool $smtpStarted, bool $stored): void
    {
        $reservation = $this->reserve();

        $this->keys->finish($reservation, $status, $body, $smtpStarted);

        $row = $this->row(self::KEY);

        if ($stored) {
            $this->assertNotNull($row);
            $this->assertSame('completed', $row['state']);
            $this->assertSame($status, (int) $row['response_status']);
            $this->assertSame($body, $row['response_body']);

            return;
        }

        $this->assertNull($row);
    }

    public function testAReleasedKeyCanBeReservedAgainWithAnotherPayload(): void
    {
        $reservation = $this->reserve();
        $this->keys->finish($reservation, 409, '{"error":{"code":"NO_SMTP_CONNECTION"}}', false);

        $result = $this->keys->begin($this->organizationId, self::KEY, $this->message('other'));

        $this->assertInstanceOf(IdempotencyReservation::class, $result);
    }

    private function reserve(): IdempotencyReservation
    {
        $result = $this->keys->begin($this->organizationId, self::KEY, $this->message());
        $this->assertInstanceOf(IdempotencyReservation::class, $result);

        return $result;
    }

    private function assertConflict(int $status, string $code, string $key, Message $message): void
    {
        try {
            $this->keys->begin($this->organizationId, $key, $message);
            $this->fail('Expected an IdempotencyConflictException');
        } catch (IdempotencyConflictException $exception) {
            $this->assertSame($status, $exception->status());
            $this->assertSame($code, $exception->errorCode());
        }
    }

    private function message(string $subject = 'Subject'): Message
    {
        return new Message('to@example.com', $subject, 'Body', null);
    }

    private function complete(string $key, int $status, string $body): void
    {
        $this->insertRow($key, $this->message(), 'completed', $status, $body);
    }

    private function insertRow(string $key, Message $message, string $state, ?int $status, ?string $body): void
    {
        $this->fixtures->insert('idempotency_keys', [
            'organization_id' => $this->organizationId,
            'key_hash' => IdempotencyKeys::keyHash($key),
            'request_hash' => IdempotencyKeys::requestHash($message),
            'state' => $state,
            'response_status' => $status,
            'response_body' => $body,
            'lease_until' => $this->at(300),
            'expires_at' => $this->at(86400),
        ]);
    }

    private function seedExpired(int $organizationId, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->fixtures->insert('idempotency_keys', [
                'organization_id' => $organizationId,
                'key_hash' => hash('sha256', "expired-{$i}"),
                'request_hash' => hash('sha256', 'r'),
                'state' => 'completed',
                'response_status' => 200,
                'response_body' => self::OK_BODY,
                'lease_until' => $this->at(-90000),
                'expires_at' => $this->at(-1),
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(string $key): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM idempotency_keys WHERE organization_id = :organization_id AND key_hash = :key_hash',
        );
        $statement->execute([
            'organization_id' => $this->organizationId,
            'key_hash' => IdempotencyKeys::keyHash($key),
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function rowCount(int $organizationId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM idempotency_keys WHERE organization_id = :id');
        $statement->execute(['id' => $organizationId]);

        return (int) $statement->fetchColumn();
    }

    private function at(int $offset): string
    {
        return (new DateTimeImmutable('@' . (self::START + $offset)))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }

    /**
     * Builds the service on a PDO that can run a hook before a statement, inside this test's transaction.
     *
     * @return array{pdo: StatementHookPdo, keys: IdempotencyKeys}
     */
    private function hookedKeys(): array
    {
        $this->pdo->rollBack();
        $config = DatabaseConfig::fromEnv();
        $factory = new PdoFactory();
        $hooked = new StatementHookPdo(
            $factory->dsn($config, true),
            $config->user,
            $config->password,
            $factory->options(),
        );
        $hooked->beginTransaction();
        $this->pdo = $hooked;
        $this->fixtures = new Fixtures($hooked);
        $this->organizationId = $this->fixtures->organization();

        return [
            'pdo' => $hooked,
            'keys' => new IdempotencyKeys(new IdempotencyKeyRepository($hooked), $this->clock),
        ];
    }
}
