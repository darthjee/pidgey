<?php

declare(strict_types=1);

// Placeholder until the front controller is built: answers every request with a static
// health payload so the Docker environment can be verified end to end.
header('Content-Type: application/json');
echo '{"status":"ok"}';
