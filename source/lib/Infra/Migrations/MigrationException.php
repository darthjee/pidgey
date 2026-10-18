<?php

declare(strict_types=1);

namespace Pidgey\Infra\Migrations;

use RuntimeException;

/**
 * Raised when migrations cannot be validated or applied.
 */
final class MigrationException extends RuntimeException
{
}
