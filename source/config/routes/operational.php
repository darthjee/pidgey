<?php

/**
 * Operational routes (health check), required from config/routes.php.
 */

declare(strict_types=1);

use Pidgey\Http\Handler\HealthHandler;
use Pidgey\Http\Router;

/** @var Router $router */
$router->get('/health.json', HealthHandler::class);
