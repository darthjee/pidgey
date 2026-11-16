<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Smtp;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Smtp\FailureCode;

/**
 * Checks that every transport failure code is reached by a test of the SMTP transport suites.
 */
#[CoversNothing]
final class FailureCodeCoverageTest extends TestCase
{
    /**
     * Every transport failure code and the test (class and method) that reaches it.
     */
    private const COVERAGE = [
        'SMTP_CONNECTION_FAILED' => [
            SmtpTransportConnectionTest::class,
            'testCloseBeforeTheGreetingIsAConnectionFailure',
        ],
        'SMTP_TLS_FAILED' => [SmtpTransportTlsTest::class, 'testStartTlsNotAdvertisedIsATlsFailure'],
        'SMTP_AUTH_FAILED' => [SmtpTransportMailpitTest::class, 'testMailpitWrongPasswordIsAnAuthFailure'],
        'SMTP_REJECTED' => [SmtpTransportFakeServerTest::class, 'testRejectedMessageAtEndOfData'],
        'SMTP_TEMPORARY_FAILURE' => [SmtpTransportFakeServerTest::class, 'testTemporaryFailureAtRcptTo'],
        'SMTP_PROTOCOL_ERROR' => [SmtpTransportFakeServerTest::class, 'testMalformedReplyIsAProtocolError'],
        'SMTP_TIMEOUT' => [SmtpTransportConnectionTest::class, 'testHangingGreetingIsATimeout'],
    ];

    /**
     * @return array<string, array{FailureCode}>
     */
    public static function failureCodes(): array
    {
        $cases = [];

        foreach (FailureCode::cases() as $code) {
            $cases[$code->value] = [$code];
        }

        return $cases;
    }

    #[DataProvider('failureCodes')]
    public function testEveryFailureCodeIsReachedByAnSmtpTransportTest(FailureCode $code): void
    {
        $this->assertArrayHasKey($code->value, self::COVERAGE);
        $this->assertTrue(method_exists(...self::COVERAGE[$code->value]));
    }
}
