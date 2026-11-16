<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Pidgey\Email\MimeBuilder;

trait SendFlowServerSteps
{
    /**
     * The greeting, `EHLO` and a successful `AUTH PLAIN` with the canary.
     *
     * @return list<array<string, string>>
     */
    protected function authenticatedSteps(): array
    {
        return [
            ['reply' => "220 fake ready\r\n"],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 AUTH PLAIN LOGIN\r\n"],
            ['expect' => 'AUTH PLAIN ' . self::authPayload(), 'reply' => "235 2.7.0 ok\r\n"],
        ];
    }

    /**
     * Reads the MIME lines after `Date:` up to (not including) the final `.`: the headers are
     * fixed in number, then the multipart body.
     *
     * @return list<array<string, string>>
     */
    protected function skipUntilDot(): array
    {
        $lines = explode("\r\n", rtrim($this->mimeTemplate(), "\r\n"));

        return array_map(
            static fn (string $line): array => ['expectPrefix' => self::stablePrefix($line)],
            array_slice($lines, 1),
        );
    }

    /**
     * The message the flow builds, minus its random parts (each line's stable prefix is used).
     */
    protected function mimeTemplate(): string
    {
        $mime = new MimeBuilder(new FrozenWallClock(1_800_000_000), static fn (): string => str_repeat("\0", 16));

        return $mime->build(self::FROM, $this->message());
    }

    protected static function stablePrefix(string $line): string
    {
        foreach (['Message-ID: <', 'Content-Type: multipart/alternative', '--=_pidgey_'] as $prefix) {
            if (str_starts_with($line, $prefix)) {
                return $prefix;
            }
        }

        return $line;
    }

    protected static function authPayload(): string
    {
        return base64_encode("\0" . self::USERNAME . "\0" . self::CANARY);
    }

    /**
     * A listening socket nobody should connect to, and its port.
     *
     * @return array{0: resource, 1: int}
     */
    protected function listener(): array
    {
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($listener);
        $name = (string) stream_socket_get_name($listener, false);

        return [$listener, (int) substr($name, (int) strrpos($name, ':') + 1)];
    }

    protected function closedPort(): int
    {
        [$listener, $port] = $this->listener();
        fclose($listener);

        return $port;
    }
}
