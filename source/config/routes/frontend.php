<?php

/**
 * Frontend routes (the built single-page application), required first from config/routes.php.
 */

declare(strict_types=1);

use Pidgey\Http\Handler\StaticFileHandler;
use Pidgey\Http\Router;

/** @var Router $router */
$router->get('/', StaticFileHandler::class);
$router->get('/assets/*', StaticFileHandler::class);
