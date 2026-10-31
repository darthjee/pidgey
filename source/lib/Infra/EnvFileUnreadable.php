<?php

declare(strict_types=1);

namespace Pidgey\Infra;

use RuntimeException;

/**
 * Raised when an env file exists but cannot be read.
 *
 * The message never holds any of the file's content.
 */
final class EnvFileUnreadable extends RuntimeException
{
}
