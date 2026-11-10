<?php

declare(strict_types=1);

namespace Pidgey\Infra;

/**
 * Locates files relative to the app root (the `source/` directory).
 */
final class AppPath
{
    /**
     * Returns the absolute path of the app root.
     *
     * @return string
     */
    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Returns an absolute path unchanged, and joins a relative one to the app root.
     *
     * @param string $path Absolute or app-root-relative path.
     *
     * @return string
     */
    public static function resolve(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return self::root() . '/' . $path;
    }
}
