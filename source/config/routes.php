<?php

/**
 * Builds the Router: the global middleware list, then one route file per area.
 *
 * Registration order matters: the first matching route wins. The frontend routes come first,
 * then the API route files, and the GET fallback (hash redirect) is registered last.
 */

declare(strict_types=1);

use Pidgey\Http\Handler\HashRedirectHandler;
use Pidgey\Http\Router;

// The global middleware list is empty until OriginCheck lands.
$router = new Router();

require __DIR__ . '/routes/frontend.php';
require __DIR__ . '/routes/operational.php';

$router->fallback(HashRedirectHandler::class);

return $router;
