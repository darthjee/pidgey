<?php

/**
 * Builds the Router: the global middleware list, then one route file per area.
 *
 * Registration order matters: the first matching route wins.
 */

declare(strict_types=1);

use Pidgey\Http\Router;

// The global middleware list is empty until OriginCheck lands.
$router = new Router();

require __DIR__ . '/routes/operational.php';

return $router;
