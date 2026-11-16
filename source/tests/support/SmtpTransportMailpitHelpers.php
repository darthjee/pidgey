<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Pidgey\Infra\AppPath;
use Pidgey\Infra\Env;
use Pidgey\Smtp\Credentials;
use Pidgey\Smtp\Envelope;
use Pidgey\Smtp\SessionSettings;
use Pidgey\Smtp\SmtpResult;
use Pidgey\Smtp\Target;
use Pidgey\Smtp\TlsMode;

trait SmtpTransportMailpitHelpers
{
    protected function sendTo(
        Mailpit $mailpit,
        TlsMode $mode,
        ?Credentials $credentials,
        ?string $message = null,
        ?SessionSettings $settings = null,
    ): SmtpResult {
        $address = gethostbyname($mailpit->host());
        $this->assertNotFalse(filter_var($address, FILTER_VALIDATE_IP), "cannot resolve {$mailpit->host()}");

        return $this->transport->send(
            new Target($mailpit->host(), $address, $mailpit->smtpPort(), $mode),
            $settings ?? $this->mailpitSettings(),
            $credentials,
            new Envelope(self::FROM, [self::TO]),
            $message ?? $this->message('unused'),
        );
    }


    /**
     * Mailpit settings: the CA of `SMTP_CA_FILE`, and the local address literal in `EHLO`.
     */
    protected function mailpitSettings(float $connectTimeout = self::TIMEOUT): SessionSettings
    {
        return new SessionSettings($connectTimeout, self::TIMEOUT, self::TIMEOUT, $this->configuredCaFile(), null);
    }


    /**
     * The CA bundle of the test environment (`SMTP_CA_FILE`), as the app resolves it.
     */
    protected function configuredCaFile(): string
    {
        $caFile = Env::get('SMTP_CA_FILE');
        $this->assertNotNull($caFile, 'SMTP_CA_FILE must be set in the test environment');

        return AppPath::resolve($caFile);
    }
}
