<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Closure;
use PHPUnit\Framework\TestCase;
use Pidgey\Smtp\Credentials;
use Pidgey\Smtp\Envelope;
use Pidgey\Smtp\SessionSettings;
use Pidgey\Smtp\SmtpFailure;
use Pidgey\Smtp\SmtpResult;
use Pidgey\Smtp\SmtpTransport;
use Pidgey\Smtp\Target;
use Pidgey\Smtp\TlsMode;

abstract class SmtpTransportTestCase extends TestCase
{
    use SmtpTransportCanaryHelpers;
    use SmtpTransportDialogueHelpers;

    protected const CANARY = 'canary-unit-marker-7c2d';
    protected const USERNAME = 'mailer@example.com';
    protected const HOST = 'smtp.example.com';
    protected const CA_FILE = '/etc/pidgey/ca.pem';
    protected const EHLO = 'EHLO [192.0.2.10]';
    protected const MESSAGE = "Subject: hi\r\n\r\nbody\r\n";

    protected FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock(1000.0);
    }

    protected function target(TlsMode $mode = TlsMode::Plain): Target
    {
        return new Target(self::HOST, '192.0.2.1', 587, $mode);
    }

    protected function settings(): SessionSettings
    {
        return new SessionSettings(10.0, 30.0, 120.0, self::CA_FILE, null);
    }

    protected function envelope(): Envelope
    {
        return new Envelope('from@example.com', ['to@example.com']);
    }

    protected function send(
        FakeSmtpStream $stream,
        ?Target $target = null,
        ?Credentials $credentials = null,
        ?SessionSettings $settings = null,
        ?Envelope $envelope = null,
        string $message = self::MESSAGE,
    ): SmtpResult {
        $transport = new SmtpTransport($this->clock, static fn (): FakeSmtpStream => $stream);

        return $transport->send(
            $target ?? $this->target(),
            $settings ?? $this->settings(),
            $credentials,
            $envelope ?? $this->envelope(),
            $message,
        );
    }

    protected function sendFailure(
        FakeSmtpStream $stream,
        ?Target $target = null,
        ?Credentials $credentials = null,
        ?SessionSettings $settings = null,
    ): SmtpFailure {
        return $this->failure(fn () => $this->send($stream, $target, $credentials, $settings));
    }

    protected function failure(Closure $call): SmtpFailure
    {
        try {
            $call();
        } catch (SmtpFailure $failure) {
            return $failure;
        }

        $this->fail('expected an SmtpFailure');
    }
}
