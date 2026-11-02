<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Pidgey\Auth\AdminCheck;
use Pidgey\Auth\Gate;
use Pidgey\Http\Router;

/**
 * Deny-by-default check of a route table (request-handler/middlewares.md#testing).
 *
 * Every route outside the public allowlist must list Gate or ApiTokenAuth, and AdminCheck must
 * come after Gate in the same middleware list. The GET fallback is public and has no route
 * middlewares.
 */
final class RouteGuards
{
    /** Routes that are reachable without credentials, as "<METHOD> <pattern>". */
    public const PUBLIC_ROUTES = ['GET /', 'GET /assets/*', 'GET /health.json'];

    /** Future Bearer middleware of the /v1 API; referenced by name until it exists. */
    public const API_TOKEN_AUTH = 'Pidgey\\Auth\\ApiTokenAuth';

    /**
     * Returns one line per violation, empty when the route table is safe.
     *
     * @return list<string>
     */
    public static function violations(Router $router): array
    {
        $violations = [];
        $global = $router->globalMiddlewares();

        foreach ($router->routes() as $route) {
            $name = $route->method() . ' ' . $route->pattern();
            $middlewares = [...$global, ...$route->middlewares()];
            $guarded = in_array(Gate::class, $middlewares, true)
                || in_array(self::API_TOKEN_AUTH, $middlewares, true);

            if (!$guarded && !in_array($name, self::PUBLIC_ROUTES, true)) {
                $violations[] = "{$name}: neither Gate nor ApiTokenAuth";
            }

            if (!self::adminCheckFollowsGate($middlewares)) {
                $violations[] = "{$name}: AdminCheck without a Gate before it";
            }
        }

        return $violations;
    }

    /**
     * Tells whether every AdminCheck in the list comes after a Gate.
     *
     * @param string[] $middlewares Global then route middleware class names.
     */
    private static function adminCheckFollowsGate(array $middlewares): bool
    {
        $admin = array_search(AdminCheck::class, $middlewares, true);

        if ($admin === false) {
            return true;
        }

        $gate = array_search(Gate::class, $middlewares, true);

        return $gate !== false && $gate < $admin;
    }
}
