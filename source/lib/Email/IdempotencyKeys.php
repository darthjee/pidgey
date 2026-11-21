<?php

declare(strict_types=1);

namespace Pidgey\Email;

use DateTimeImmutable;
use JsonException;
use LogicException;
use PDOException;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\IdempotencyConflictException;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Infra\WallClock;
use Pidgey\Model\IdempotencyKey;
use Pidgey\Model\IdempotencyState;
use Pidgey\Repository\IdempotencyKeyRepository;

/**
 * The `Idempotency-Key` rules of `POST /v1/messages` (`email/send-flow.md#idempotency-key-step`),
 * independent of HTTP: validates and hashes the key, decides what a request with a key does and
 * stores or releases the answer.
 *
 * No lock is held across the SMTP transaction: the reservation relies on the unique index only.
 * The raw key and the message content are never stored, only their hashes.
 */
final class IdempotencyKeys
{
    /** How long a key is kept, in seconds (24 hours). */
    public const TTL = 86400;

    /** How long an in-flight reservation is trusted, in seconds (5 minutes). */
    public const LEASE = 300;

    /** Most expired rows of an organization deleted when a key is reserved. */
    public const CLEANUP_BATCH = 100;

    private const KEY_FORMAT = '/^[\x21-\x7E]{1,255}$/D';

    private const KEY_INVALID = 'Idempotency-Key must be 1 to 255 printable ASCII characters';

    private const TIMEOUT_CODE = 'SMTP_TIMEOUT';

    private const TIMEOUT_MESSAGE = 'The SMTP server timed out';

    private const PROTOCOL_ERROR_CODE = 'SMTP_PROTOCOL_ERROR';

    /** Reads of a key that lost the reservation race before giving up as in use. */
    private const MAX_ATTEMPTS = 3;

    /**
     * Builds the service.
     *
     * @param IdempotencyKeyRepository $repository The `idempotency_keys` repository.
     * @param WallClock                $clock      Source of the lease, expiry and envelope times.
     */
    public function __construct(
        private readonly IdempotencyKeyRepository $repository,
        private readonly WallClock $clock,
    ) {
    }

    /**
     * Tells whether a key has the valid format: 1 to 255 printable ASCII characters.
     *
     * @param string $key Raw header value.
     *
     * @return boolean
     */
    public static function isValid(string $key): bool
    {
        return preg_match(self::KEY_FORMAT, $key) === 1;
    }

    /**
     * Refuses a key with an invalid format.
     *
     * @param string $key Raw header value.
     *
     * @return void
     *
     * @throws ValidationFailedException When the key is empty, too long or has another character.
     */
    public static function validate(string $key): void
    {
        if (!self::isValid($key)) {
            throw new ValidationFailedException([self::KEY_INVALID]);
        }
    }

    /**
     * Returns the SHA-256 of the raw key, as lowercase hex.
     *
     * @param string $key Raw header value.
     *
     * @return string
     */
    public static function keyHash(string $key): string
    {
        return hash('sha256', $key);
    }

    /**
     * Returns the SHA-256 of the normalized payload, as lowercase hex: `to`, `subject`, `text`
     * and `html` in this order, an absent field as null.
     *
     * @param Message $message The validated message.
     *
     * @return string
     *
     * @throws JsonException When a field is not valid UTF-8.
     */
    public static function requestHash(Message $message): string
    {
        return hash('sha256', json_encode(
            ['to' => $message->to, 'subject' => $message->subject, 'text' => $message->text, 'html' => $message->html],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
    }

    /**
     * Decides what a request with a key does.
     *
     * Returns a reservation when the request must send (the caller later calls `finish()`), or
     * a replay of the stored answer. An expired row counts as absent.
     *
     * @param integer $organizationId Organization of the API token.
     * @param string  $key            Valid raw header value.
     * @param Message $message        The validated message.
     *
     * @return IdempotencyReservation|IdempotencyReplay
     *
     * @throws IdempotencyConflictException When the key was used with another payload (422) or
     *                                      is still in progress (409).
     * @throws JsonException                When a field of the message is not valid UTF-8.
     * @throws PDOException                 When a statement fails.
     * @throws LogicException               When a completed row has no stored answer.
     */
    public function begin(int $organizationId, string $key, Message $message): IdempotencyReservation|IdempotencyReplay
    {
        $keyHash = self::keyHash($key);
        $requestHash = self::requestHash($message);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $now = $this->clock->now();
            $row = $this->repository->find($organizationId, $keyHash);

            if ($row !== null && $row->expiresAt > $now) {
                return $this->decide($organizationId, $keyHash, $requestHash, $row, $now);
            }

            $reserved = $this->repository->reserve(
                $organizationId,
                $keyHash,
                $requestHash,
                $now,
                $now->modify('+' . self::LEASE . ' seconds'),
                $now->modify('+' . self::TTL . ' seconds'),
            );

            if ($reserved) {
                $this->repository->deleteExpired($organizationId, $now, self::CLEANUP_BATCH);

                return new IdempotencyReservation($organizationId, $keyHash);
            }
        }

        throw IdempotencyConflictException::inUse();
    }

    /**
     * Stores the answer of a reserved key, or releases the key.
     *
     * Stored (the key replays it): `200`, `504 SMTP_TIMEOUT`, `502 SMTP_PROTOCOL_ERROR` and a
     * `500` raised after the SMTP transaction started, because the message may have been
     * delivered. Released (a retry really sends): every other answer.
     *
     * @param IdempotencyReservation $reservation The handle returned by `begin()`.
     * @param integer                $status      HTTP status of the answer.
     * @param string                 $body        JSON body of the answer.
     * @param boolean                $smtpStarted Whether the SMTP transaction had started.
     *
     * @return void
     *
     * @throws PDOException When a statement fails.
     */
    public function finish(IdempotencyReservation $reservation, int $status, string $body, bool $smtpStarted): void
    {
        if (!$this->isStored($status, $body, $smtpStarted)) {
            $this->repository->release($reservation->organizationId, $reservation->keyHash);

            return;
        }

        $this->repository->complete($reservation->organizationId, $reservation->keyHash, $status, $body);
    }

    /**
     * Decides on a live row of the key.
     *
     * @param integer           $organizationId Organization of the API token.
     * @param string            $keyHash        Hash of the raw key.
     * @param string            $requestHash    Hash of the payload.
     * @param IdempotencyKey    $row            The live row.
     * @param DateTimeImmutable $now            Current time.
     *
     * @return IdempotencyReplay
     *
     * @throws IdempotencyConflictException When the payload differs or the request is in progress.
     * @throws LogicException               When a completed row has no stored answer.
     */
    private function decide(
        int $organizationId,
        string $keyHash,
        string $requestHash,
        IdempotencyKey $row,
        DateTimeImmutable $now,
    ): IdempotencyReplay {
        if ($row->requestHash !== $requestHash) {
            throw IdempotencyConflictException::reused();
        }

        if ($row->state === IdempotencyState::Completed) {
            return self::replayOf($row);
        }

        if ($row->leaseUntil > $now) {
            throw IdempotencyConflictException::inUse();
        }

        $body = (new ErrorResponse(504, self::TIMEOUT_CODE, self::TIMEOUT_MESSAGE, null, null, $now))->body();
        $this->repository->complete($organizationId, $keyHash, 504, $body);

        return new IdempotencyReplay(504, $body);
    }

    /**
     * Builds the replay of a completed row.
     *
     * @param IdempotencyKey $row The completed row.
     *
     * @return IdempotencyReplay
     *
     * @throws LogicException When the row has no stored answer.
     */
    private static function replayOf(IdempotencyKey $row): IdempotencyReplay
    {
        if ($row->responseStatus === null || $row->responseBody === null) {
            throw new LogicException('IdempotencyKeys: a completed key has no stored answer');
        }

        return new IdempotencyReplay($row->responseStatus, $row->responseBody);
    }

    /**
     * Tells whether an answer is stored (delivery may have happened) rather than released.
     *
     * @param integer $status      HTTP status of the answer.
     * @param string  $body        JSON body of the answer.
     * @param boolean $smtpStarted Whether the SMTP transaction had started.
     *
     * @return boolean
     */
    private function isStored(int $status, string $body, bool $smtpStarted): bool
    {
        return match ($status) {
            200, 504 => true,
            502 => self::errorCode($body) === self::PROTOCOL_ERROR_CODE,
            500 => $smtpStarted,
            default => false,
        };
    }

    /**
     * Reads `error.code` of an error envelope.
     *
     * @param string $body JSON body.
     *
     * @return string|null
     */
    private static function errorCode(string $body): ?string
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $code = is_array($data) && is_array($data['error'] ?? null) ? ($data['error']['code'] ?? null) : null;

        return is_string($code) ? $code : null;
    }
}
