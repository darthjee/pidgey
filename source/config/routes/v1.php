<?php

/**
 * Public API routes (`/v1`), required from config/routes.php.
 *
 * Authenticated by a Bearer API token (ApiTokenAuth) and a scope check, never by the Gate: the
 * two credentials are disjoint (see api/authentication/api-tokens.md#disjoint-credentials). The
 * guards are per route, with no prefix-wide guard. The global OriginCheck runs first.
 */

declare(strict_types=1);

use Pidgey\Auth\ApiTokenAuth;
use Pidgey\Auth\RequireSendScope;
use Pidgey\Http\Handler\SendMessageHandler;
use Pidgey\Http\Router;

/** @var Router $router */
$router->post('/v1/messages', SendMessageHandler::class, [ApiTokenAuth::class, RequireSendScope::class]);
