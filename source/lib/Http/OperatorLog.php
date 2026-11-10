<?php

declare(strict_types=1);

namespace Pidgey\Http;

use Psr\Log\LoggerInterface;

/**
 * Writes the operator log line of a successful admin state change (see
 * `api/endpoints/pidgey-admin.md#operator-log`).
 *
 * One `info` line per change: the message is the action name, the context holds the acting
 * user's id, the organization id, the connection id and, for updates, the changed field
 * **names**. Never values, passwords or tokens.
 */
final class OperatorLog
{
    public const SMTP_CONNECTION_CREATE = 'smtp-connection-create';

    public const SMTP_CONNECTION_UPDATE = 'smtp-connection-update';

    public const SMTP_CONNECTION_DELETE = 'smtp-connection-delete';

    public const SMTP_CONNECTION_ACTIVATE = 'smtp-connection-activate';

    public const SMTP_CONNECTION_DEACTIVATE = 'smtp-connection-deactivate';

    /**
     * Builds the log on the application logger.
     *
     * @param LoggerInterface $logger The application logger (LoggerFactory).
     */
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * Writes one operator log line.
     *
     * @param string            $action         Action name, e.g. `smtp-connection-update`.
     * @param integer           $userId         The acting user's id.
     * @param integer           $organizationId The organization's id.
     * @param integer           $connectionId   The SMTP connection id.
     * @param list<string>|null $fields         Changed field names (updates only), never values.
     *
     * @return void
     */
    public function record(
        string $action,
        int $userId,
        int $organizationId,
        int $connectionId,
        ?array $fields = null,
    ): void {
        $context = ['userId' => $userId, 'organizationId' => $organizationId, 'connectionId' => $connectionId];

        if ($fields !== null) {
            $context['fields'] = $fields;
        }

        $this->logger->info($action, $context);
    }
}
