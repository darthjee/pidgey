<?php

declare(strict_types=1);

namespace Pidgey\Infra;

/**
 * Process-wide PHP settings applied by every entrypoint, right after the env file is loaded.
 */
final class Runtime
{
    /**
     * Outside development and test (an unset `PHP_ENV` counts as production), keeps call
     * arguments out of exception traces, so that a secret passed to a function never reaches
     * a log line.
     *
     * @return void
     */
    public static function harden(): void
    {
        if (in_array(Env::get('PHP_ENV'), ConfigValidator::NON_PRODUCTION_ENVIRONMENTS, true)) {
            return;
        }

        ini_set('zend.exception_ignore_args', '1');
    }
}
