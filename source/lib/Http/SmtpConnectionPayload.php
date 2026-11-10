<?php

declare(strict_types=1);

namespace Pidgey\Http;

use JsonSerializable;
use Pidgey\Model\SmtpConnection;

/**
 * The SMTP connection item of the `/smtp-connections*.json` bodies (see
 * `api/endpoints/pidgey-admin/smtp-connections.md#smtp-connection-item`).
 *
 * Never carries the password (plain or encrypted) nor the organization id; `hasPassword` is
 * the only hint that one is stored.
 */
final readonly class SmtpConnectionPayload implements JsonSerializable
{
    /**
     * Wraps a connection.
     *
     * @param SmtpConnection $connection The stored row.
     */
    public function __construct(private SmtpConnection $connection)
    {
    }

    /**
     * Builds the item.
     *
     * @return array{
     *     id: integer,
     *     name: string,
     *     host: string,
     *     port: integer,
     *     encryption: string,
     *     username: string|null,
     *     hasPassword: boolean,
     *     fromAddress: string,
     *     active: boolean,
     *     createdAt: string,
     *     updatedAt: string
     * }
     */
    public function jsonSerialize(): array
    {
        $connection = $this->connection;

        return [
            'id' => $connection->id,
            'name' => $connection->name,
            'host' => $connection->host,
            'port' => $connection->port,
            'encryption' => $connection->encryption->value,
            'username' => $connection->username,
            'hasPassword' => $connection->hasPassword(),
            'fromAddress' => $connection->fromAddress,
            'active' => $connection->active,
            'createdAt' => Timestamp::format($connection->createdAt),
            'updatedAt' => Timestamp::format($connection->updatedAt),
        ];
    }

    /**
     * Debug view without the encrypted password bytes.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return $this->jsonSerialize();
    }
}
