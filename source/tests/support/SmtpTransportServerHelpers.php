<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Pidgey\Smtp\Credentials;
use Pidgey\Smtp\Envelope;
use Pidgey\Smtp\SessionSettings;
use Pidgey\Smtp\SmtpResult;
use Pidgey\Smtp\Target;
use Pidgey\Smtp\TlsMode;

trait SmtpTransportServerHelpers
{
    /**
     * @param list<array<string, mixed>> $steps
     */
    protected function startServer(array $steps): void
    {
        $this->server = FakeSmtpServer::start(['steps' => $steps]);
    }

    protected function assertServerClean(): void
    {
        $this->assertNotNull($this->server);
        $this->server->assertClean();
    }

    /**
     * The server steps of `MAIL FROM`, `RCPT TO`, `DATA` and the message lines, up to the `.`.
     *
     * @return list<array<string, mixed>>
     */
    protected function envelopeSteps(): array
    {
        return [
            ['expect' => 'MAIL FROM:<' . self::FROM . '>', 'reply' => "250 ok\r\n"],
            ['expect' => 'RCPT TO:<' . self::TO . '>', 'reply' => "250 ok\r\n"],
            ['expect' => 'DATA', 'reply' => "354 go ahead\r\n"],
            ['expect' => 'Subject: fake'],
            ['expect' => ''],
            ['expect' => 'body'],
            ['expect' => '..'],
        ];
    }


    protected function sendToServer(
        TlsMode $mode,
        ?Credentials $credentials = null,
        ?SessionSettings $settings = null,
    ): SmtpResult {
        $this->assertNotNull($this->server);

        return $this->transport->send(
            new Target('localhost', $this->server->host(), $this->server->port(), $mode),
            $settings ?? $this->fakeSettings(),
            $credentials,
            new Envelope(self::FROM, [self::TO]),
            "Subject: fake\r\n\r\nbody\r\n.\r\n",
        );
    }

    protected function fakeSettings(
        float $connectTimeout = self::TIMEOUT,
        float $commandTimeout = self::TIMEOUT,
    ): SessionSettings {
        $caFile = $this->fixtureCa();

        return new SessionSettings($connectTimeout, $commandTimeout, self::TIMEOUT, $caFile, self::EHLO_NAME);
    }


    protected function fixtureCa(): string
    {
        return (string) realpath(self::FIXTURE_CA);
    }


    protected function closedPort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($server);
        $name = (string) stream_socket_get_name($server, false);
        fclose($server);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }
}
