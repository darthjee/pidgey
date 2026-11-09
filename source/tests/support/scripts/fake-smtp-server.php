<?php

/**
 * Fake SMTP server for tests, started by `Pidgey\Tests\Support\FakeSmtpServer`.
 *
 * Usage: php fake-smtp-server.php <scenario.json>
 *
 * Listens on 127.0.0.1 (ephemeral port), prints `PORT=<n>`, serves one scripted session and
 * exits: 0 when every step ran and the client closed, 1 with a message on stderr when the
 * client deviates from the scenario. Before hanging (`then: hang`) it prints `HANG`, so a
 * server stopped while hanging counts as clean.
 *
 * A failed TLS handshake (wrong-name / untrusted tests) is expected: the server exits 0.
 */

declare(strict_types=1);

const FIXTURES = __DIR__ . '/../../fixtures/tls';
const DRIP_GAP_MICROSECONDS = 50000;
const ACCEPT_TIMEOUT_SECONDS = 30;
const READ_TIMEOUT_SECONDS = 30;

/**
 * Writes the message on stderr and exits 1.
 */
function abortScenario(string $message): never
{
    fwrite(STDERR, "fake-smtp-server: {$message}\n");

    exit(1);
}

/**
 * Runs the server-side TLS handshake; a failure ends the session cleanly (exit 0).
 *
 * @param resource $client
 */
function enableTls($client): void
{
    stream_set_blocking($client, true);

    if (@stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLS_SERVER) !== true) {
        exit(0);
    }
}

/**
 * Reads one client line, without its line ending.
 *
 * @param resource $client
 */
function readClientLine($client): string
{
    $line = @fgets($client);

    if ($line === false) {
        abortScenario('client closed or timed out before the expected line');
    }

    return rtrim($line, "\r\n");
}

/**
 * Checks the next client line against the step's `expect` or `expectPrefix`.
 *
 * @param resource             $client
 * @param array<string, mixed> $step
 */
function expectLine($client, array $step, int $index): void
{
    if (isset($step['expect'])) {
        $line = readClientLine($client);

        if ($line !== $step['expect']) {
            abortScenario(sprintf('step %d: expected "%s", got "%s"', $index, $step['expect'], $line));
        }
    } elseif (isset($step['expectPrefix'])) {
        $line = readClientLine($client);

        if (!str_starts_with($line, (string) $step['expectPrefix'])) {
            abortScenario(sprintf('step %d: expected prefix "%s", got "%s"', $index, $step['expectPrefix'], $line));
        }
    }
}

/**
 * Sends the step's reply, whole or one byte at a time.
 *
 * @param resource $client
 */
function sendReply($client, string $reply, bool $drip): void
{
    if (!$drip) {
        @fwrite($client, $reply);

        return;
    }

    foreach (str_split($reply) as $byte) {
        @fwrite($client, $byte);
        @fflush($client);
        usleep(DRIP_GAP_MICROSECONDS);
    }
}

/**
 * Applies the step's `then`.
 *
 * @param resource             $client
 * @param array<string, mixed> $step
 */
function applyThen($client, array $step): void
{
    $then = $step['then'] ?? null;

    if ($then === null) {
        return;
    }

    if ($then === 'close') {
        fclose($client);

        exit(0);
    }

    if ($then === 'hang') {
        fwrite(STDOUT, "HANG\n");
        fflush(STDOUT);

        while (true) {
            sleep(1);
        }
    }

    if ($then === 'starttls') {
        enableTls($client);
    } elseif ($then !== 'inject') {
        abortScenario('unknown then: ' . (string) $then);
    }
}

/**
 * Waits for the client to close; any byte it still sends breaks the scenario.
 *
 * @param resource $client
 */
function awaitClientClose($client): void
{
    while (true) {
        $data = @fread($client, 8192);

        if ($data === false || ($data === '' && feof($client))) {
            return;
        }

        if ($data !== '') {
            abortScenario('unexpected client data after the last step: "' . rtrim($data, "\r\n") . '"');
        }

        if (stream_get_meta_data($client)['timed_out']) {
            abortScenario('client did not close after the last step');
        }
    }
}

$scenario = json_decode((string) file_get_contents($argv[1] ?? ''), true, 512, JSON_THROW_ON_ERROR);
$cert = (string) ($scenario['cert'] ?? 'server');

$context = stream_context_create(['ssl' => [
    'local_cert' => FIXTURES . "/{$cert}.pem",
    'local_pk' => FIXTURES . "/{$cert}-key.pem",
]]);
$server = stream_socket_server(
    'tcp://127.0.0.1:0',
    $errorCode,
    $errorMessage,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
    $context,
);

if ($server === false) {
    abortScenario("cannot listen: {$errorMessage}");
}

$address = (string) stream_socket_get_name($server, false);
fwrite(STDOUT, 'PORT=' . substr($address, strrpos($address, ':') + 1) . "\n");
fflush(STDOUT);

$client = @stream_socket_accept($server, ACCEPT_TIMEOUT_SECONDS);

if ($client === false) {
    abortScenario('no client connected');
}

stream_set_timeout($client, READ_TIMEOUT_SECONDS);

if (($scenario['mode'] ?? 'plain') === 'ssl') {
    enableTls($client);
}

foreach ($scenario['steps'] ?? [] as $index => $step) {
    expectLine($client, $step, $index);
    usleep((int) ($step['delayMs'] ?? 0) * 1000);
    // Injected bytes go out in the same write as the reply, so they reach the client with it.
    $injected = ($step['then'] ?? null) === 'inject' ? (string) ($step['bytes'] ?? '') : '';
    sendReply($client, (string) ($step['reply'] ?? '') . $injected, (bool) ($step['drip'] ?? false));
    applyThen($client, $step);
}

awaitClientClose($client);

exit(0);
