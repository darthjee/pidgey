<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Email\SendOutcome;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\HttpException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\Response;
use Pidgey\Http\SmtpFailureException;
use Pidgey\Infra\WallClock;
use Pidgey\Model\SmtpEncryption;
use Psr\Log\NullLogger;

#[CoversClass(SmtpFailureException::class)]
#[UsesClass(SendOutcome::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(ErrorFormatter::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(Response::class)]
final class SmtpFailureExceptionTest extends TestCase
{
    private const REPLY_CODE = 550;
    private const REPLY = '5.1.1 User unknown';

    /**
     * Every row: the outcome's code, step and whether it has a reply; the connection's port and
     * encryption; the expected status, message and whether `error.smtp` is present.
     *
     * @return iterable<string, array{
     *     0: string, 1: ?string, 2: bool, 3: int, 4: SmtpEncryption, 5: int, 6: string, 7: bool
     * }>
     */
    public static function rows(): iterable
    {
        $insecure = 'The SMTP connection has credentials but no encryption; set encryption to tls or ssl';
        $denied = 'The SMTP host resolves to an address that is not allowed';
        $unreadable = 'The stored SMTP password could not be decrypted; set it again';
        $tls = 'TLS negotiation with the SMTP server failed';
        $timeout = 'The SMTP server timed out';

        yield 'insecure credentials' => [
            'SMTP_INSECURE_CREDENTIALS', 'send-time-check', false, 25, SmtpEncryption::None,
            409, $insecure, false,
        ];
        yield 'host not allowed' => [
            'SMTP_HOST_NOT_ALLOWED', 'send-time-check', false, 587, SmtpEncryption::Tls,
            409, $denied, false,
        ];
        yield 'password unreadable' => [
            'SMTP_PASSWORD_UNREADABLE', 'send-time-check', false, 587, SmtpEncryption::Tls,
            409, $unreadable, false,
        ];
        yield 'connection failed' => [
            'SMTP_CONNECTION_FAILED', 'connect', false, 587, SmtpEncryption::Tls,
            502, 'Could not connect to the SMTP server', false,
        ];
        yield 'connection failed, reply ignored' => [
            'SMTP_CONNECTION_FAILED', 'greeting', true, 587, SmtpEncryption::Tls,
            502, 'Could not connect to the SMTP server', false,
        ];

        yield 'tls failed without reply' => [
            'SMTP_TLS_FAILED', 'connect', false, 465, SmtpEncryption::Ssl,
            502, $tls, false,
        ];
        yield 'tls failed with the STARTTLS reply' => [
            'SMTP_TLS_FAILED', 'starttls', true, 587, SmtpEncryption::Tls,
            502, $tls, true,
        ];
        yield 'tls hint 587 ssl' => [
            'SMTP_TLS_FAILED', 'connect', false, 587, SmtpEncryption::Ssl,
            502, $tls . '; port 587 usually uses encryption tls', false,
        ];
        yield 'tls hint 25 ssl' => [
            'SMTP_TLS_FAILED', 'connect', false, 25, SmtpEncryption::Ssl,
            502, $tls . '; port 25 usually uses encryption tls', false,
        ];
        yield 'tls no hint 2525 ssl' => [
            'SMTP_TLS_FAILED', 'connect', false, 2525, SmtpEncryption::Ssl,
            502, $tls, false,
        ];
        yield 'tls no hint 465 ssl' => [
            'SMTP_TLS_FAILED', 'connect', false, 465, SmtpEncryption::Ssl,
            502, $tls, false,
        ];
        yield 'tls no hint 587 tls' => [
            'SMTP_TLS_FAILED', 'starttls', false, 587, SmtpEncryption::Tls,
            502, $tls, false,
        ];

        yield 'auth rejected credentials' => [
            'SMTP_AUTH_FAILED', 'auth', true, 587, SmtpEncryption::Tls,
            502, 'The SMTP server rejected the credentials', true,
        ];
        yield 'auth no mechanism' => [
            'SMTP_AUTH_FAILED', 'auth', false, 587, SmtpEncryption::Tls,
            502, 'The SMTP server offers no supported authentication mechanism', false,
        ];
        yield 'auth required' => [
            'SMTP_AUTH_FAILED', 'mail-from', true, 25, SmtpEncryption::None,
            502, 'The SMTP server requires authentication', true,
        ];

        yield 'rejected greeting' => [
            'SMTP_REJECTED', 'greeting', true, 25, SmtpEncryption::None,
            502, 'The SMTP server rejected the connection', true,
        ];
        yield 'rejected ehlo' => [
            'SMTP_REJECTED', 'ehlo', true, 25, SmtpEncryption::None,
            502, 'The SMTP server rejected the connection', true,
        ];
        yield 'rejected sender' => [
            'SMTP_REJECTED', 'mail-from', true, 25, SmtpEncryption::None,
            502, 'The SMTP server rejected the sender', true,
        ];
        yield 'rejected recipient' => [
            'SMTP_REJECTED', 'rcpt-to', true, 25, SmtpEncryption::None,
            502, 'The SMTP server rejected the recipient', true,
        ];
        yield 'rejected data' => [
            'SMTP_REJECTED', 'data', true, 25, SmtpEncryption::None,
            502, 'The SMTP server rejected the message', true,
        ];
        yield 'rejected message' => [
            'SMTP_REJECTED', 'end-of-data', true, 25, SmtpEncryption::None,
            502, 'The SMTP server rejected the message', true,
        ];

        yield 'temporary failure' => [
            'SMTP_TEMPORARY_FAILURE', 'rcpt-to', true, 587, SmtpEncryption::Tls,
            503, 'The SMTP server is temporarily unavailable; try again later', true,
        ];
        yield 'protocol error' => [
            'SMTP_PROTOCOL_ERROR', 'ehlo', false, 587, SmtpEncryption::Tls,
            502, 'The SMTP server sent an invalid reply', false,
        ];
        yield 'protocol error, reply ignored' => [
            'SMTP_PROTOCOL_ERROR', 'data', true, 587, SmtpEncryption::Tls,
            502, 'The SMTP server sent an invalid reply', false,
        ];

        yield 'timeout' => ['SMTP_TIMEOUT', 'greeting', false, 587, SmtpEncryption::Tls, 504, $timeout, false];
        yield 'timeout hint 465 tls' => [
            'SMTP_TIMEOUT', 'greeting', false, 465, SmtpEncryption::Tls,
            504, $timeout . '; port 465 usually requires encryption ssl', false,
        ];
        yield 'timeout hint 465 none' => [
            'SMTP_TIMEOUT', 'greeting', false, 465, SmtpEncryption::None,
            504, $timeout . '; port 465 usually requires encryption ssl', false,
        ];
        yield 'timeout no hint 465 ssl' => [
            'SMTP_TIMEOUT', 'connect', false, 465, SmtpEncryption::Ssl,
            504, $timeout, false,
        ];
        yield 'timeout no hint 2465 tls' => [
            'SMTP_TIMEOUT', 'greeting', false, 2465, SmtpEncryption::Tls,
            504, $timeout, false,
        ];
        yield 'timeout, reply ignored' => [
            'SMTP_TIMEOUT', 'data', true, 587, SmtpEncryption::Tls,
            504, $timeout, false,
        ];
    }

    #[DataProvider('rows')]
    public function testMapsTheOutcome(
        string $code,
        ?string $step,
        bool $hasReply,
        int $port,
        SmtpEncryption $encryption,
        int $status,
        string $message,
        bool $hasSmtp,
    ): void {
        $outcome = $hasReply
            ? SendOutcome::failed($code, $step, self::REPLY_CODE, self::REPLY, null)
            : SendOutcome::failed($code, $step, null, null, null);

        $exception = SmtpFailureException::fromOutcome($outcome, $port, $encryption);

        $this->assertSame($status, $exception->status());
        $this->assertSame($code, $exception->errorCode());
        $this->assertSame($message, $exception->errorMessage());
        $this->assertSame($message, $exception->getMessage());
        $this->assertNull($exception->details());
        $this->assertSame($hasSmtp ? ['code' => self::REPLY_CODE, 'message' => self::REPLY] : null, $exception->smtp());
    }

    public function testNeverCarriesTheTlsReason(): void
    {
        $reason = 'certificate verify failed';
        $outcome = SendOutcome::failed('SMTP_TLS_FAILED', 'starttls', 454, 'TLS not available', $reason);

        $exception = SmtpFailureException::fromOutcome($outcome, 587, SmtpEncryption::Tls);

        $this->assertStringNotContainsString('certificate', $exception->getMessage());
        $this->assertStringNotContainsString('certificate', (string) json_encode($exception->smtp()));
        $this->assertStringNotContainsString('certificate', $this->format($exception));
    }

    public function testFormatsTheJsonBody(): void
    {
        $outcome = SendOutcome::failed('SMTP_REJECTED', 'rcpt-to', 550, '5.1.1 User unknown', null);

        $body = $this->format(SmtpFailureException::fromOutcome($outcome, 587, SmtpEncryption::Tls));

        $this->assertSame(
            '{"error":{"code":"SMTP_REJECTED","message":"The SMTP server rejected the recipient",'
            . '"smtp":{"code":550,"message":"5.1.1 User unknown"}},'
            . '"statusCode":502,"timestamp":"2026-10-25T12:00:00.000Z"}',
            $body,
        );
    }

    public function testFormatsASendTimeFailureWithoutSmtp(): void
    {
        $outcome = SendOutcome::failed('SMTP_HOST_NOT_ALLOWED', 'send-time-check', null, null, null);

        $body = $this->format(SmtpFailureException::fromOutcome($outcome, 25, SmtpEncryption::None));

        $this->assertSame(
            '{"error":{"code":"SMTP_HOST_NOT_ALLOWED",'
            . '"message":"The SMTP host resolves to an address that is not allowed"},'
            . '"statusCode":409,"timestamp":"2026-10-25T12:00:00.000Z"}',
            $body,
        );
    }

    public function testRefusesASentOutcome(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('SmtpFailureException: the outcome was sent');

        SmtpFailureException::fromOutcome(SendOutcome::sent(250, 'Ok'), 587, SmtpEncryption::Tls);
    }

    public function testRefusesAnUnknownCode(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('SmtpFailureException: unknown failure code');

        $outcome = SendOutcome::failed('SMTP_BOGUS', null, null, null, null);

        SmtpFailureException::fromOutcome($outcome, 587, SmtpEncryption::Tls);
    }

    private function format(SmtpFailureException $exception): string
    {
        $clock = new class implements WallClock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-25T12:00:00.000Z');
            }
        };

        return (new ErrorFormatter($clock, new NullLogger()))->format($exception)->body();
    }
}
