<?php

declare(strict_types=1);

use Pidgey\Http\FrontController;
use Pidgey\Infra\EnvFile;
use Pidgey\Infra\Runtime;

require __DIR__ . '/../vendor/autoload.php';

EnvFile::load(__DIR__ . '/../config/.env');
Runtime::harden();

FrontController::run();
