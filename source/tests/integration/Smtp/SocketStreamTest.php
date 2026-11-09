<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Smtp;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\AppPath;
use Pidgey\Infra\Env;
use Pidgey\Infra\SystemClock;
use Pidgey\Smtp\Deadline;
use Pidgey\Smtp\EhloExtensions;
use Pidgey\Smtp\MalformedReply;
use Pidgey\Smtp\Reply;
use Pidgey\Smtp\ReplyParser;
use Pidgey\Smtp\SocketStream;
use Pidgey\Smtp\StreamFailure;
use Pidgey\Smtp\StreamFailureKind;
use Pidgey\Smtp\TlsContext;
use Pidgey\Tests\Support\FakeSmtpServer;
use Pidgey\Tests\Support\Mailpit;

#[CoversClass(SocketStream::class)]
#[UsesClass(AppPath::class)]
#[UsesClass(Deadline::class)]
#[UsesClass(EhloExtensions::class)]
#[UsesClass(Env::class)]
#[UsesClass(MalformedReply::class)]
#[UsesClass(Reply::class)]
#[UsesClass(ReplyParser::class)]
#[UsesClass(StreamFailure::class)]
#[UsesClass(StreamFailureKind::class)]
#[UsesClass(SystemClock::class)]
#[UsesClass(TlsContext::class)]
final class SocketStreamTest extends TestCase
{
    private const FIXTURE_CA = __DIR__ . '/../../fixtures/tls/ca.pem';
    private const TIMEOUT = 5.0;
    private const SHORT_TIMEOUT = 0.3;

    private SocketStream $stream;

    private ?FakeSmtpServer $server = null;

    protected function setUp(): void
    {
        $this->stream = new SocketStream();
    }

    protected function tearDown(): void
    {
        $this->stream->close();
        $this->server?->stop();
    }

    public function testPlainGreetingAndCommand(): void
    {
        $this->startServer([
            'steps' => [
                ['reply' => "220 fake ready\r\n"],
                ['expect' => 'EHLO client.example', 'reply' => "250-fake\r\n250 STARTTLS\r\n"],
            ],
        ]);
        $this->openPlain();

        $this->assertSame("220 fake ready\r\n", $this->stream->readLine($this->deadline()));
        $this->assertSame('127.0.0.1', $this->stream->localAddress());
        $this->stream->writeLine('EHLO client.example', $this->deadline());
        $reply = (new ReplyParser())->read($this->stream, $this->deadline());
        $this->assertSame(['fake', 'STARTTLS'], $reply->lines());

        $this->stream->close();
        $this->stream->close();
        $this->assertServerClean();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function trustedPeerNames(): array
    {
        return [
            'host name' => ['localhost'],
            'ip literal through the IP SAN' => ['127.0.0.1'],
        ];
    }

    #[DataProvider('trustedPeerNames')]
    public function testImplicitTlsVerifiesThePeer(string $peerName): void
    {
        $this->startServer(['mode' => 'ssl', 'steps' => [['reply' => "220 secure\r\n"]]]);

        $this->openTls($peerName);

        $this->assertSame("220 secure\r\n", $this->stream->readLine($this->deadline()));
        $this->stream->close();
        $this->assertServerClean();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedCertificates(): array
    {
        return [
            'wrong name' => ['wrong-name'],
            'untrusted' => ['untrusted'],
        ];
    }

    #[DataProvider('rejectedCertificates')]
    public function testImplicitTlsRejectsABadCertificate(string $cert): void
    {
        $this->startServer(['mode' => 'ssl', 'cert' => $cert, 'steps' => [['reply' => "220 secure\r\n"]]]);

        $failure = $this->failure(fn () => $this->openTls('localhost'));

        $this->assertSame(StreamFailureKind::Tls, $failure->kind());
        $this->assertNotEmpty($failure->reason());
        $this->assertSame('TLS negotiation failed', $failure->getMessage());
    }

    public function testImplicitTlsWithoutTheTestCaIsRejected(): void
    {
        $this->startServer(['mode' => 'ssl', 'steps' => [['reply' => "220 secure\r\n"]]]);

        $failure = $this->failure(
            fn () => $this->stream->open('127.0.0.1', $this->port(), 'localhost', true, null, $this->deadline()),
        );

        $this->assertSame(StreamFailureKind::Tls, $failure->kind());
    }

    public function testStarttlsUpgrade(): void
    {
        $this->startServer([
            'mode' => 'starttls',
            'steps' => [
                ['reply' => "220 ready\r\n"],
                ['expect' => 'STARTTLS', 'reply' => "220 go ahead\r\n", 'then' => 'starttls'],
                ['expect' => 'EHLO client.example', 'reply' => "250 secure\r\n"],
            ],
        ]);
        $this->openPlain();
        $this->stream->readLine($this->deadline());
        $this->stream->writeLine('STARTTLS', $this->deadline());
        $this->assertSame("220 go ahead\r\n", $this->stream->readLine($this->deadline()));

        $this->stream->upgradeToTls('localhost', $this->caFile(), $this->deadline());
        $this->stream->writeLine('EHLO client.example', $this->deadline());

        $this->assertSame("250 secure\r\n", $this->stream->readLine($this->deadline()));
        $this->stream->close();
        $this->assertServerClean();
    }

    public function testStarttlsRejectsAWrongName(): void
    {
        $this->startServer([
            'cert' => 'wrong-name',
            'steps' => [
                ['reply' => "220 ready\r\n"],
                ['expect' => 'STARTTLS', 'reply' => "220 go ahead\r\n", 'then' => 'starttls'],
            ],
        ]);
        $this->openPlain();
        $this->stream->readLine($this->deadline());
        $this->stream->writeLine('STARTTLS', $this->deadline());
        $this->stream->readLine($this->deadline());

        $failure = $this->failure(fn () => $this->upgrade());

        $this->assertSame(StreamFailureKind::Tls, $failure->kind());
        $this->assertNotEmpty($failure->reason());
    }

    public function testBytesBeforeTheHandshakeAreUnexpectedData(): void
    {
        $this->startServer([
            'steps' => [
                ['reply' => "220 ready\r\n"],
                [
                    'expect' => 'STARTTLS',
                    'reply' => "220 go ahead\r\n",
                    'then' => 'inject',
                    'bytes' => "250 injected\r\n",
                ],
            ],
        ]);
        $this->openPlain();
        $this->stream->readLine($this->deadline());
        $this->stream->writeLine('STARTTLS', $this->deadline());
        $this->assertSame("220 go ahead\r\n", $this->stream->readLine($this->deadline()));

        $failure = $this->failure(fn () => $this->upgrade());

        $this->assertSame(StreamFailureKind::UnexpectedData, $failure->kind());
        $this->assertNull($failure->reason());
        $this->stream->close();
        $this->assertServerClean();
    }

    public function testSilentServerTimesOut(): void
    {
        $this->startServer(['steps' => [['then' => 'hang']]]);
        $this->openPlain();
        $started = microtime(true);

        $failure = $this->failure(fn () => $this->stream->readLine($this->deadline(self::SHORT_TIMEOUT)));

        $this->assertSame(StreamFailureKind::Timeout, $failure->kind());
        $this->assertElapsedAround($started, self::SHORT_TIMEOUT);
    }

    public function testDrippedReplyCannotOutliveTheReplyBudget(): void
    {
        $this->startServer(['steps' => [['reply' => "250-slow\r\n250 slower\r\n", 'drip' => true]]]);
        $this->openPlain();
        $started = microtime(true);

        $failure = $this->failure(
            fn () => (new ReplyParser())->read($this->stream, $this->deadline(self::SHORT_TIMEOUT)),
        );

        $this->assertSame(StreamFailureKind::Timeout, $failure->kind());
        $this->assertElapsedAround($started, self::SHORT_TIMEOUT);
    }

    public function testExpiredDeadlineFailsWithoutIo(): void
    {
        $this->startServer(['steps' => [['reply' => "220 ready\r\n"]]]);
        $this->openPlain();
        $expired = $this->deadline(0.0);

        $this->assertSame(
            StreamFailureKind::Timeout,
            $this->failure(fn () => $this->stream->readLine($expired))->kind(),
        );
        $this->assertSame(
            StreamFailureKind::Timeout,
            $this->failure(fn () => $this->stream->writeLine('NOOP', $expired))->kind(),
        );
        $this->assertSame("220 ready\r\n", $this->stream->readLine($this->deadline()));
    }

    public function testCloseBeforeTheGreetingIsClosed(): void
    {
        $this->startServer(['steps' => [['then' => 'close']]]);
        $this->openPlain();

        $failure = $this->failure(fn () => $this->stream->readLine($this->deadline()));

        $this->assertSame(StreamFailureKind::Closed, $failure->kind());
    }

    public function testCloseMidLineReturnsThePartialLine(): void
    {
        $this->startServer(['steps' => [['reply' => '250 partial', 'then' => 'close']]]);
        $this->openPlain();

        $this->assertSame('250 partial', $this->stream->readLine($this->deadline()));
        $this->assertSame(
            StreamFailureKind::Closed,
            $this->failure(fn () => $this->stream->readLine($this->deadline()))->kind(),
        );
    }

    public function testOverlongLineIsReturnedWithoutLineFeed(): void
    {
        $this->startServer(['steps' => [['reply' => '250 ' . str_repeat('x', 3000) . "\r\n"]]]);
        $this->openPlain();

        $line = $this->stream->readLine($this->deadline());

        $this->assertSame(ReplyParser::MAX_LINE_BYTES + 2, strlen($line));
        $this->assertStringEndsNotWith("\n", $line);
    }

    public function testRefusedConnectionIsAConnectFailure(): void
    {
        $failure = $this->failure(
            fn () => $this->stream->open('127.0.0.1', $this->closedPort(), 'localhost', false, null, $this->deadline()),
        );

        $this->assertSame(StreamFailureKind::Connect, $failure->kind());
        $this->assertSame('SMTP connection failed', $failure->getMessage());
    }

    public function testExpiredDeadlineBeforeConnectingIsAConnectFailure(): void
    {
        $failure = $this->failure(
            fn () => $this->stream->open('127.0.0.1', 25, 'localhost', false, null, $this->deadline(0.0)),
        );

        $this->assertSame(StreamFailureKind::Connect, $failure->kind());
    }

    public function testMailpitPlainGreeting(): void
    {
        $mailpit = Mailpit::plain();

        $this->openMailpit($mailpit);

        $this->assertSame(220, (new ReplyParser())->read($this->stream, $this->deadline())->code());
    }

    public function testMailpitStarttlsUpgradeIsVerified(): void
    {
        $mailpit = Mailpit::starttls();
        $parser = new ReplyParser();
        $this->openMailpit($mailpit);
        $this->assertSame(220, $parser->read($this->stream, $this->deadline())->code());

        $this->stream->writeLine('EHLO client.example', $this->deadline());
        $before = EhloExtensions::fromReply($parser->read($this->stream, $this->deadline()));
        $this->assertTrue($before->has('STARTTLS'));
        $this->stream->writeLine('STARTTLS', $this->deadline());
        $this->assertSame(220, $parser->read($this->stream, $this->deadline())->code());

        $this->stream->upgradeToTls($mailpit->host(), $this->configuredCaFile(), $this->deadline());
        $this->stream->writeLine('EHLO client.example', $this->deadline());

        $after = EhloExtensions::fromReply($parser->read($this->stream, $this->deadline()));
        $this->assertContains('PLAIN', $after->authMechanisms());
        $this->stream->writeLine('QUIT', $this->deadline());
        $this->assertSame(221, $parser->read($this->stream, $this->deadline())->code());
    }

    public function testMailpitImplicitTlsIsVerified(): void
    {
        $mailpit = Mailpit::ssl();

        $this->stream->open(
            $this->resolve($mailpit),
            $mailpit->smtpPort(),
            $mailpit->host(),
            true,
            $this->configuredCaFile(),
            $this->deadline(),
        );

        $this->assertSame(220, (new ReplyParser())->read($this->stream, $this->deadline())->code());
    }

    public function testMailpitImplicitTlsUnderAnotherNameIsRejected(): void
    {
        $mailpit = Mailpit::ssl();

        $failure = $this->failure(fn () => $this->stream->open(
            $this->resolve($mailpit),
            $mailpit->smtpPort(),
            'not-the-mailpit-host.example',
            true,
            $this->configuredCaFile(),
            $this->deadline(),
        ));

        $this->assertSame(StreamFailureKind::Tls, $failure->kind());
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function startServer(array $scenario): void
    {
        $this->server = FakeSmtpServer::start($scenario);
    }

    private function port(): int
    {
        $this->assertNotNull($this->server);

        return $this->server->port();
    }

    private function openPlain(): void
    {
        $this->stream->open('127.0.0.1', $this->port(), 'localhost', false, $this->caFile(), $this->deadline());
    }

    private function openTls(string $peerName): void
    {
        $this->stream->open('127.0.0.1', $this->port(), $peerName, true, $this->caFile(), $this->deadline());
    }

    private function upgrade(): void
    {
        $this->stream->upgradeToTls('localhost', $this->caFile(), $this->deadline());
    }

    private function openMailpit(Mailpit $mailpit): void
    {
        $address = $this->resolve($mailpit);

        $this->stream->open($address, $mailpit->smtpPort(), $mailpit->host(), false, null, $this->deadline());
    }

    private function assertServerClean(): void
    {
        $this->assertNotNull($this->server);
        $this->server->assertClean();
    }

    private function deadline(float $seconds = self::TIMEOUT): Deadline
    {
        return Deadline::start(new SystemClock(), $seconds);
    }

    private function caFile(): string
    {
        return (string) realpath(self::FIXTURE_CA);
    }

    /**
     * The CA bundle of the test environment (`SMTP_CA_FILE`), as the app resolves it.
     */
    private function configuredCaFile(): string
    {
        $caFile = Env::get('SMTP_CA_FILE');
        $this->assertNotNull($caFile, 'SMTP_CA_FILE must be set in the test environment');

        return AppPath::resolve($caFile);
    }

    private function resolve(Mailpit $mailpit): string
    {
        $address = gethostbyname($mailpit->host());
        $this->assertNotFalse(filter_var($address, FILTER_VALIDATE_IP), "cannot resolve {$mailpit->host()}");

        return $address;
    }

    private function closedPort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($server);
        $name = (string) stream_socket_get_name($server, false);
        fclose($server);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    private function failure(Closure $call): StreamFailure
    {
        try {
            $call();
        } catch (StreamFailure $failure) {
            return $failure;
        }

        $this->fail('expected a stream failure');
    }

    private function assertElapsedAround(float $started, float $timeout): void
    {
        $elapsed = microtime(true) - $started;

        $this->assertGreaterThanOrEqual($timeout - 0.01, $elapsed);
        $this->assertLessThan($timeout + 1.0, $elapsed);
    }
}
