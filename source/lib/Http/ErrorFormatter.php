<?php

declare(strict_types=1);

namespace Pidgey\Http;

use Closure;
use Pidgey\Infra\SystemWallClock;
use Pidgey\Infra\WallClock;
use Throwable;

/**
 * The single error formatter: turns any Throwable into the JSON error envelope.
 */
final class ErrorFormatter
{
    private const INTERNAL_CODE = 'INTERNAL_ERROR';

    private const INTERNAL_MESSAGE = 'Internal server error';

    /** Category codes from api/conventions/errors.md; other statuses get HTTP_<status>. */
    private const CATEGORY_CODES = [
        400 => 'BAD_REQUEST',
        401 => 'UNAUTHORIZED',
        403 => 'FORBIDDEN',
        404 => 'NOT_FOUND',
        409 => 'CONFLICT',
        423 => 'LOCKED',
        429 => 'TOO_MANY_REQUESTS',
        500 => 'INTERNAL_ERROR',
    ];

    /** Standard reason phrases for the error statuses the API can answer. */
    private const REASON_PHRASES = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        413 => 'Content Too Large',
        415 => 'Unsupported Media Type',
        422 => 'Unprocessable Content',
        423 => 'Locked',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];

    private readonly WallClock $clock;

    private readonly Closure $logger;

    /**
     * Builds the formatter.
     *
     * @param WallClock|null $clock  Source of the envelope timestamp; defaults to SystemWallClock.
     * @param callable|null  $logger Receives the log line of an unexpected error; defaults to error_log().
     *                               Monolog replaces it once logging is wired.
     */
    public function __construct(?WallClock $clock = null, ?callable $logger = null)
    {
        $this->clock = $clock ?? new SystemWallClock();
        $this->logger = $logger === null
            ? static fn (string $line) => error_log($line)
            : Closure::fromCallable($logger);
    }

    /**
     * Formats the error.
     *
     * An HttpException gives its own envelope, with the category code and the reason phrase
     * as fallbacks. Any other Throwable gives 500 INTERNAL_ERROR; the real error is logged
     * and never sent.
     *
     * @param Throwable $error The error to format.
     *
     * @return ErrorResponse
     */
    public function format(Throwable $error): ErrorResponse
    {
        if ($error instanceof HttpException) {
            return $this->formatHttpException($error);
        }

        ($this->logger)(sprintf(
            'Unhandled %s: %s in %s:%d',
            $error::class,
            $error->getMessage(),
            $error->getFile(),
            $error->getLine(),
        ));

        return new ErrorResponse(500, self::INTERNAL_CODE, self::INTERNAL_MESSAGE, null, null, $this->clock->now());
    }

    /**
     * Builds the envelope of an HttpException.
     *
     * @param HttpException $error The exception to format.
     *
     * @return ErrorResponse
     */
    private function formatHttpException(HttpException $error): ErrorResponse
    {
        $status = $error->status();

        return new ErrorResponse(
            $status,
            $error->errorCode() ?? self::CATEGORY_CODES[$status] ?? 'HTTP_' . $status,
            $error->errorMessage() ?? self::REASON_PHRASES[$status] ?? 'HTTP ' . $status,
            $error->details(),
            $error->smtp(),
            $this->clock->now(),
        );
    }
}
