<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use Pidgey\Http\OperatorLog;

/**
 * `POST /smtp-connections/:id/deactivate.json` (gated): makes the connection inactive; other
 * connections are never changed; idempotent (see
 * `api/endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsiddeactivatejson`).
 *
 * Deactivating the active connection leaves the organization with no active connection.
 */
final class SmtpConnectionDeactivateHandler extends SmtpConnectionStateHandler
{
    /**
     * Deactivates the target only.
     *
     * @param integer $organizationId The caller's organization.
     * @param integer $id             Connection id.
     *
     * @return void
     */
    protected function apply(int $organizationId, int $id): void
    {
        $this->connections->deactivate($organizationId, $id);
    }

    /**
     * Returns `smtp-connection-deactivate`.
     *
     * @return string
     */
    protected function action(): string
    {
        return OperatorLog::SMTP_CONNECTION_DEACTIVATE;
    }
}
