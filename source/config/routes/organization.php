<?php

/**
 * Organization-area routes (SMTP connections; the organization and API-token routes join
 * later), required from config/routes.php.
 *
 * Every route is gated by the Gate middleware (cookie access token of an approved user), with
 * no admin check. The global OriginCheck rejects cross-site POSTs, PATCHes and DELETEs before
 * the gate (see api/endpoints/pidgey-admin.md#guards).
 */

declare(strict_types=1);

use Pidgey\Auth\Gate;
use Pidgey\Http\Handler\SmtpConnectionActivateHandler;
use Pidgey\Http\Handler\SmtpConnectionDeactivateHandler;
use Pidgey\Http\Handler\SmtpConnectionHandler;
use Pidgey\Http\Handler\SmtpConnectionsHandler;
use Pidgey\Http\Router;

/** @var Router $router */
$router->get('/smtp-connections.json', SmtpConnectionsHandler::class, [Gate::class]);
$router->post('/smtp-connections.json', SmtpConnectionsHandler::class, [Gate::class]);
$router->get('/smtp-connections/:id.json', SmtpConnectionHandler::class, [Gate::class]);
$router->patch('/smtp-connections/:id.json', SmtpConnectionHandler::class, [Gate::class]);
$router->delete('/smtp-connections/:id.json', SmtpConnectionHandler::class, [Gate::class]);
$router->post('/smtp-connections/:id/activate.json', SmtpConnectionActivateHandler::class, [Gate::class]);
$router->post('/smtp-connections/:id/deactivate.json', SmtpConnectionDeactivateHandler::class, [Gate::class]);
