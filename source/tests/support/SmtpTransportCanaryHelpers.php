<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Pidgey\Infra\SecretString;
use Pidgey\Smtp\Credentials;
use Throwable;

trait SmtpTransportCanaryHelpers
{
    protected function credentials(): Credentials
    {
        return new Credentials(self::USERNAME, new SecretString(self::CANARY));
    }

    protected function plainPayload(): string
    {
        return base64_encode("\0" . self::USERNAME . "\0" . self::CANARY);
    }

    protected function assertNoCanary(Throwable $failure): void
    {
        $this->assertSame([], $this->leaks($failure), 'a secret leaked into an exception message');
    }

    /**
     * Returns the secret forms found in the messages of the exception chain.
     *
     * @return list<string>
     */
    protected function leaks(Throwable $failure): array
    {
        $secrets = [
            self::CANARY,
            base64_encode(self::CANARY),
            $this->plainPayload(),
            'AUTH PLAIN ' . $this->plainPayload(),
        ];
        $found = [];

        for ($current = $failure; $current !== null; $current = $current->getPrevious()) {
            foreach ($secrets as $secret) {
                if (str_contains($current->getMessage(), $secret)) {
                    $found[] = $secret;
                }
            }
        }

        return $found;
    }
}
