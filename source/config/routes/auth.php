<?php

/**
 * Public session routes (register, login, refresh, logoff, status), required from
 * config/routes.php.
 *
 * No route middlewares: every one is deliberately ungated (each authenticates through the
 * refresh token or credentials in its body), and the global OriginCheck rejects cross-site
 * POSTs and DELETEs before the handler (see api/endpoints/auth.md#guards).
 */

declare(strict_types=1);

use Pidgey\Http\Handler\LoginHandler;
use Pidgey\Http\Handler\LogoffHandler;
use Pidgey\Http\Handler\RefreshHandler;
use Pidgey\Http\Handler\RegisterHandler;
use Pidgey\Http\Handler\StatusHandler;
use Pidgey\Http\Router;

/** @var Router $router */
$router->post('/auth/register.json', RegisterHandler::class);
$router->post('/auth/login.json', LoginHandler::class);
$router->post('/auth/refresh.json', RefreshHandler::class);
$router->delete('/auth/logoff.json', LogoffHandler::class);
$router->post('/auth/status.json', StatusHandler::class);
