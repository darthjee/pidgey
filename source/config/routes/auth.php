<?php

/**
 * Public session routes (register, login), required from config/routes.php.
 *
 * No route middlewares: both are deliberately ungated, and the global OriginCheck rejects
 * cross-site POSTs before the handler (see api/endpoints/auth.md#guards).
 */

declare(strict_types=1);

use Pidgey\Http\Handler\LoginHandler;
use Pidgey\Http\Handler\RegisterHandler;
use Pidgey\Http\Router;

/** @var Router $router */
$router->post('/auth/register.json', RegisterHandler::class);
$router->post('/auth/login.json', LoginHandler::class);
