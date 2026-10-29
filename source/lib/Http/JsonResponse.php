<?php

declare(strict_types=1);

namespace Pidgey\Http;

use JsonException;
use JsonSerializable;

/**
 * Response whose body is JSON-encoded data.
 *
 * Keys are sent as given: handlers build camelCase keys themselves.
 */
final class JsonResponse extends Response
{
    private const FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * Encodes the data and adds the JSON Content-Type header.
     *
     * @param array<mixed>|JsonSerializable $data   Data to encode.
     * @param integer                       $status HTTP status code.
     *
     * @throws JsonException When the data can't be encoded.
     */
    public function __construct(array|JsonSerializable $data, int $status = 200)
    {
        parent::__construct(json_encode($data, self::FLAGS), $status, ['Content-Type: application/json']);
    }
}
