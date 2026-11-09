<?php

declare(strict_types=1);

namespace Pidgey\Infra;

use InvalidArgumentException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Builds the application logger: one `pidgey` channel, one handler, one line per record.
 *
 * No processors are added, so nothing request-derived (URL, IP, headers) is attached to the
 * records behind the caller's back. Stack traces are off, because the arguments in a trace may
 * carry secrets such as passwords or message bodies.
 */
final class LoggerFactory
{
    /** Name of the single log channel. */
    public const CHANNEL = 'pidgey';

    /** Valid `LOG_LEVEL` values, exact and lower case. */
    public const LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

    /** `LOG_LEVEL` used when the variable is unset or empty. */
    public const DEFAULT_LEVEL = 'info';

    /** Line format, the same as Monolog's `ErrorLogHandler` default. */
    public const FORMAT = "%channel%.%level_name%: %message% %context% %extra%";

    /**
     * Builds a logger that handles records at the given minimum level and above.
     *
     * @param string                $level   Minimum level, one of `LEVELS`.
     * @param HandlerInterface|null $handler Destination of the records; defaults to an
     *                                       `ErrorLogHandler`.
     *
     * @return LoggerInterface
     *
     * @throws InvalidArgumentException When the level is not one of `LEVELS`; the message never
     *                                  includes the value.
     */
    public static function create(string $level, ?HandlerInterface $handler = null): LoggerInterface
    {
        if (!in_array($level, self::LEVELS, true)) {
            throw new InvalidArgumentException('LOG_LEVEL: invalid level');
        }

        $handler ??= new ErrorLogHandler();

        if ($handler instanceof AbstractHandler) {
            $handler->setLevel(Level::fromName($level));
        }

        if ($handler instanceof FormattableHandlerInterface) {
            $handler->setFormatter(new LineFormatter(self::FORMAT, null, false, false, false));
        }

        return new Logger(self::CHANNEL, [$handler]);
    }

    /**
     * Builds the logger from `LOG_LEVEL`, defaulting to `DEFAULT_LEVEL` when unset or empty.
     *
     * @return LoggerInterface
     *
     * @throws InvalidArgumentException When `LOG_LEVEL` is not one of `LEVELS`.
     */
    public static function fromEnv(): LoggerInterface
    {
        return self::create((string) Env::get('LOG_LEVEL', self::DEFAULT_LEVEL));
    }
}
