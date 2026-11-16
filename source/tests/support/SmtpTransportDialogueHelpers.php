<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Pidgey\Smtp\SmtpStep;

trait SmtpTransportDialogueHelpers
{
    protected function stream(string $localAddress = '192.0.2.10'): FakeSmtpStream
    {
        return new FakeSmtpStream($this->clock, $localAddress);
    }

    protected function happyStream(): FakeSmtpStream
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $this->envelopeAndData($stream);
        $this->quit($stream);

        return $stream;
    }

    protected function greet(FakeSmtpStream $stream): void
    {
        $stream->expectOpen()->reply("220 mx.example.com ESMTP\r\n");
    }

    protected function ehlo(FakeSmtpStream $stream, string ...$extensions): void
    {
        $lines = ['mx.example.com', ...$extensions];
        $raw = '';

        foreach ($lines as $index => $line) {
            $raw .= '250' . ($index === count($lines) - 1 ? ' ' : '-') . $line . "\r\n";
        }

        $stream->expectLine(self::EHLO)->reply($raw);
    }

    protected function login(FakeSmtpStream $stream): void
    {
        $stream
            ->expectLine('AUTH LOGIN')->reply("334 VXNlcm5hbWU6\r\n")
            ->expectLine(base64_encode(self::USERNAME))->reply("334 UGFzc3dvcmQ6\r\n")
            ->expectLine(base64_encode(self::CANARY))->reply("235 2.7.0 ok\r\n");
    }

    /**
     * @param list<string> $lines
     */
    protected function envelopeAndData(
        FakeSmtpStream $stream,
        array $lines = ['Subject: hi', '', 'body'],
        string $final = "250 2.0.0 queued as ABC\r\n",
        float $finalDelay = 0.0,
    ): void {
        $stream
            ->expectLine('MAIL FROM:<from@example.com>')->reply("250 2.1.0 ok\r\n")
            ->expectLine('RCPT TO:<to@example.com>')->reply("250 2.1.5 ok\r\n")
            ->expectLine('DATA')->reply("354 go ahead\r\n");

        foreach ($lines as $line) {
            $stream->expectLine($line);
        }

        $stream->expectLine('.')->reply($final, $finalDelay);
    }

    /**
     * Scripts the envelope up to the step, which gets the raw reply.
     */
    protected function upTo(FakeSmtpStream $stream, SmtpStep $step, string $raw): void
    {
        $steps = [
            [SmtpStep::MailFrom, 'MAIL FROM:<from@example.com>', "250 ok\r\n"],
            [SmtpStep::RcptTo, 'RCPT TO:<to@example.com>', "250 ok\r\n"],
            [SmtpStep::Data, 'DATA', "354 go\r\n"],
        ];

        foreach ($steps as [$current, $line, $reply]) {
            $stream->expectLine($line)->reply($current === $step ? $raw : $reply);

            if ($current === $step) {
                return;
            }
        }

        $stream->expectLine('Subject: hi')->expectLine('')->expectLine('body')->expectLine('.')->reply($raw);
    }

    protected function quit(FakeSmtpStream $stream): void
    {
        $stream->expectLine('QUIT')->reply("221 2.0.0 bye\r\n");
    }
}
