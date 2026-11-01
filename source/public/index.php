<?php

declare(strict_types=1);

use Pidgey\Http\FrontController;
use Pidgey\Infra\EnvFile;

require __DIR__ . '/../vendor/autoload.php';

EnvFile::load(__DIR__ . '/../config/.env');

FrontController::run();
