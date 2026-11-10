<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Support;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Pidgey\Tests\Support\FakeSmtpServer;

#[CoversNothing]
final class FakeSmtpServerTest extends TestCase
{
    private const CA_FILE = __DIR__ . '/../../fixtures/tls/ca.pem';

    public function testStartsOnAnEphemeralPortOfTheLoopback(): void
    {
        $server = FakeSmtpServer::start(['steps' => [['reply' => "220 ready\r\n"]]]);

        $this->assertSame('127.0.0.1', $server->host());
        $this->assertGreaterThan(0, $server->port());

        $client = $this->connect($server);
        fclose($client);
        $server->assertClean();
    }

    public function testPlainRoundTrip(): void
    {
        $server = FakeSmtpServer::start([
            'mode' => 'plain',
            'steps' => [
                ['reply' => "220 fake ready\r\n"],
                ['expect' => 'EHLO client.example', 'reply' => "250-fake\r\n250 SIZE 1000\r\n"],
                ['expectPrefix' => 'MAIL FROM:', 'reply' => "250 OK\r\n"],
                ['expect' => 'QUIT', 'reply' => "221 bye\r\n", 'then' => 'close'],
            ],
        ]);
        $client = $this->connect($server);

        $this->assertSame("220 fake ready\r\n", fgets($client));
        fwrite($client, "EHLO client.example\r\n");
        $this->assertSame("250-fake\r\n", fgets($client));
        $this->assertSame("250 SIZE 1000\r\n", fgets($client));
        fwrite($client, "MAIL FROM:<a@example.com>\r\n");
        $this->assertSame("250 OK\r\n", fgets($client));
        fwrite($client, "QUIT\r\n");
        $this->assertSame("221 bye\r\n", fgets($client));
        $this->assertSame('', (string) fread($client, 10));
        $this->assertTrue(feof($client));
        fclose($client);

        $server->assertClean();
    }

    public function testMismatchedLineMakesAssertCleanFail(): void
    {
        $server = FakeSmtpServer::start([
            'steps' => [
                ['reply' => "220 ready\r\n"],
                ['expect' => 'EHLO client.example', 'reply' => "250 OK\r\n"],
            ],
        ]);
        $client = $this->connect($server);
        fgets($client);
        fwrite($client, "HELO client.example\r\n");
        $this->assertFalse(fgets($client));
        fclose($client);

        $this->assertSame(1, $server->stop());
        $this->assertStringContainsString('HELO client.example', $server->stderr());

        $this->expectException(AssertionFailedError::class);

        $server->assertClean();
    }

    public function testDataAfterTheLastStepIsAFailure(): void
    {
        $server = FakeSmtpServer::start(['steps' => [['reply' => "220 ready\r\n"]]]);
        $client = $this->connect($server);
        fgets($client);
        fwrite($client, "NOOP\r\n");
        fclose($client);

        $this->assertSame(1, $server->stop());
    }

    public function testStopAfterHangReturnsPromptlyAndClean(): void
    {
        $server = FakeSmtpServer::start(['steps' => [['reply' => "220 ready\r\n", 'then' => 'hang']]]);
        $client = $this->connect($server);
        fgets($client);

        $started = microtime(true);
        $server->assertClean();

        $this->assertLessThan(1.0, microtime(true) - $started);
        fclose($client);
    }

    public function testStopIsIdempotent(): void
    {
        $server = FakeSmtpServer::start(['steps' => [['then' => 'close']]]);
        fclose($this->connect($server));

        $this->assertSame(0, $server->stop());
        $this->assertSame(0, $server->stop());
    }

    public function testDripSendsTheReplyByteByByte(): void
    {
        $server = FakeSmtpServer::start(['steps' => [['reply' => "220 ok\r\n", 'drip' => true]]]);
        $client = $this->connect($server);

        $started = microtime(true);
        $this->assertSame("220 ok\r\n", fgets($client));

        $this->assertGreaterThanOrEqual(0.3, microtime(true) - $started);
        fclose($client);
        $server->assertClean();
    }

    public function testDelayIsAppliedBeforeTheReply(): void
    {
        $server = FakeSmtpServer::start(['steps' => [['reply' => "220 ok\r\n", 'delayMs' => 300]]]);
        $client = $this->connect($server);

        $started = microtime(true);
        $this->assertSame("220 ok\r\n", fgets($client));

        $this->assertGreaterThanOrEqual(0.25, microtime(true) - $started);
        fclose($client);
        $server->assertClean();
    }

    public function testImplicitTlsWithTheIpSan(): void
    {
        $server = FakeSmtpServer::start(['mode' => 'ssl', 'steps' => [['reply' => "220 secure\r\n"]]]);
        $context = stream_context_create(['ssl' => [
            'cafile' => self::CA_FILE,
            'peer_name' => '127.0.0.1',
            'SNI_enabled' => false,
        ]]);
        $client = stream_socket_client(
            "ssl://127.0.0.1:{$server->port()}",
            $errorCode,
            $errorMessage,
            5,
            STREAM_CLIENT_CONNECT,
            $context,
        );
        $this->assertIsResource($client, "[{$errorCode}] {$errorMessage}");

        $this->assertSame("220 secure\r\n", fgets($client));
        fclose($client);
        $server->assertClean();
    }

    public function testStarttlsThenInject(): void
    {
        $server = FakeSmtpServer::start([
            'mode' => 'starttls',
            'steps' => [
                ['reply' => "220 ready\r\n"],
                ['expect' => 'STARTTLS', 'reply' => "220 go\r\n", 'then' => 'inject', 'bytes' => "250 injected\r\n"],
            ],
        ]);
        $client = $this->connect($server);
        fgets($client);
        fwrite($client, "STARTTLS\r\n");

        $this->assertSame("220 go\r\n", fgets($client));
        $this->assertSame("250 injected\r\n", fgets($client));
        fclose($client);
        $server->assertClean();
    }

    /**
     * @return resource
     */
    private function connect(FakeSmtpServer $server)
    {
        $client = stream_socket_client("tcp://{$server->host()}:{$server->port()}", $errorCode, $errorMessage, 5);
        $this->assertIsResource($client, "[{$errorCode}] {$errorMessage}");
        stream_set_timeout($client, 5);

        return $client;
    }
}
