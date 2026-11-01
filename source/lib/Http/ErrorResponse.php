<?php

declare(strict_types=1);

namespace Pidgey\Http;

use DateTimeImmutable;
use DateTimeZone;

/**
 * JSON error envelope: {"error":{"code","message"[,"details"][,"smtp"]},"statusCode","timestamp"}.
 */
class ErrorResponse extends JsonResponse
{
    private const TIMESTAMP_FORMAT = 'Y-m-d\TH:i:s.v\Z';

    /**
     * Builds the envelope; details and smtp are omitted when null.
     *
     * @param integer                   $status    HTTP status code, repeated as statusCode.
     * @param string                    $code      Error code.
     * @param string                    $message   Human-readable message.
     * @param list<string>|null         $details   Validation details, or null to omit them.
     * @param array<string, mixed>|null $smtp      SMTP server reply, or null to omit it.
     * @param DateTimeImmutable         $timestamp When the error happened; sent in UTC with milliseconds.
     */
    public function __construct(
        int $status,
        string $code,
        string $message,
        ?array $details,
        ?array $smtp,
        DateTimeImmutable $timestamp,
    ) {
        $error = ['code' => $code, 'message' => $message];

        if ($details !== null) {
            $error['details'] = $details;
        }

        if ($smtp !== null) {
            $error['smtp'] = $smtp;
        }

        parent::__construct(
            [
                'error' => $error,
                'statusCode' => $status,
                'timestamp' => $timestamp->setTimezone(new DateTimeZone('UTC'))->format(self::TIMESTAMP_FORMAT),
            ],
            $status,
        );
    }
}
