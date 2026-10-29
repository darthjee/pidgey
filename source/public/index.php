<?php

declare(strict_types=1);

use Pidgey\Http\Handler\HealthHandler;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;
use Pidgey\Http\ResponseSender;

require __DIR__ . '/../vendor/autoload.php';

// Until the router exists, every request is answered by HealthHandler.
$request = new ProcessingRequest(new Request());
$response = (new HealthHandler())->handle($request, []);
(new ResponseSender())->send($response);
