<?php

declare(strict_types=1);

namespace Pidgey\Model;

/**
 * State of an idempotency key (`idempotency_keys.state`); each case's value is the database value.
 */
enum IdempotencyState: string
{
    case InFlight = 'in_flight';
    case Completed = 'completed';
}
