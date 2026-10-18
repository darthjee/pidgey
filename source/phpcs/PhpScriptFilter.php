<?php

declare(strict_types=1);

namespace Pidgey\Lint;

use PHP_CodeSniffer\Filters\Filter;

/**
 * PHP_CodeSniffer file filter that also accepts extensionless PHP scripts in `bin/`.
 *
 * phpcs only checks files by extension; the `bin/` entry points have none, so they are
 * recognized by their `#!/usr/bin/env php` shebang instead.
 */
final class PhpScriptFilter extends Filter
{
    private const SHEBANG = '#!/usr/bin/env php';

    /**
     * Tells whether the file should be checked.
     *
     * @param string|\SplFileInfo $path Path of the file.
     *
     * @return boolean
     */
    protected function shouldProcessFile($path)
    {
        if (parent::shouldProcessFile($path)) {
            return true;
        }

        $path = (string) $path;

        return basename(dirname($path)) === 'bin' && $this->isPhpScript($path);
    }

    /**
     * Tells whether the file starts with a PHP shebang.
     *
     * @param string $path Path of the file.
     *
     * @return boolean
     */
    private function isPhpScript(string $path): bool
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            return false;
        }

        $firstLine = fgets($handle);
        fclose($handle);

        return $firstLine !== false && rtrim($firstLine) === self::SHEBANG;
    }
}
