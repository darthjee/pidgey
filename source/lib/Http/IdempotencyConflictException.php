<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * An `Idempotency-Key` that cannot be used: reused with another payload (422) or still in
 * progress (409). The messages are fixed.
 */
final class IdempotencyConflictException extends HttpException
{
    /**
     * The key was already used with a different payload.
     *
     * @return self
     */
    public static function reused(): self
    {
        return new self(422, 'IDEMPOTENCY_KEY_REUSED', 'Idempotency-Key was already used with a different payload');
    }

    /**
     * A request with the key is still in progress.
     *
     * @return self
     */
    public static function inUse(): self
    {
        return new self(
            409,
            'IDEMPOTENCY_KEY_IN_USE',
            'A request with this Idempotency-Key is still in progress',
        );
    }
}
