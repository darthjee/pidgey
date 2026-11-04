<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use RuntimeException;

/**
 * Internal sentinel of `SessionRotation`: thrown inside the rotation transaction when the
 * conditional revoke affected no row, so the transaction rolls back. It never leaves
 * `SessionRotation::rotate()` and carries no token.
 *
 * @internal
 */
final class RotationLost extends RuntimeException
{
}
