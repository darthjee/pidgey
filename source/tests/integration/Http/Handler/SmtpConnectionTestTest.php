<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pidgey\Container;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\FrontController;
use Pidgey\Http\Handler\SmtpConnectionTestHandler;
use Pidgey\Http\Request;
use Pidgey\Http\Response;
use Pidgey\Infra\PdoFactory;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\UserRepository;
use Pidgey\Smtp\HostPolicy;
use Pidgey\Smtp\HostResolver;
use Pidgey\Tests\Support\Mailpit;
use Pidgey\Tests\Support\SmtpConnectionEndpointTestCase;
use RuntimeException;

/**
 * `POST /smtp-connections/:id/test.json` through the real routes, delivering to Mailpit.
 */
#[CoversClass(SmtpConnectionTestHandler::class)]
final class SmtpConnectionTestTest extends SmtpConnectionEndpointTestCase
{
    private const NOT_FOUND = 'SMTP connection not found';

    private const NOT_A_USER = 'to must be the email of a user of the organization';

    private const MAX_ATTEMPTS = 3;

    private const VARIABLES = ['SMTP_ALLOW_PRIVATE_HOSTS', 'SMTP_TEST_MAX_ATTEMPTS', 'SMTP_TEST_WINDOW'];

    /** @var array<string, string|false> */
    private array $savedSettings = [];

    private Mailpit $mailpit;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::VARIABLES as $name) {
            $this->savedSettings[$name] = getenv($name);
        }

        putenv('SMTP_ALLOW_PRIVATE_HOSTS=true');
        putenv('SMTP_TEST_MAX_ATTEMPTS=' . self::MAX_ATTEMPTS);
        putenv('SMTP_TEST_WINDOW=3600');
        $this->mailpit = Mailpit::plain();
        $this->mailpit->clear();
        Mailpit::starttls()->clear();
    }

    protected function tearDown(): void
    {
        foreach ($this->savedSettings as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }

        parent::tearDown();
    }

    public function testSendsToTheCallerByDefault(): void
    {
        $user = $this->member('Caller@Example.com');
        $id = $this->plainConnection($user);

        $response = $this->send($user, 'POST', self::path($id));

        $this->assertSame(201, $response->status(), $response->body());
        $body = self::json($response);
        $this->assertSame('sent', $body['status']);
        $this->assertSame(250, $body['smtp']['code']);
        $this->assertNotSame('', $body['smtp']['message']);
        $this->assertSame(['Caller@Example.com'], $this->recipients());
        $this->assertSame('Pidgey test email', $this->mailpit->messages()[0]['Subject']);
        $this->assertNull($this->limitRow($user)['busy_until']);
        $this->assertSame(1, (int) $this->limitRow($user)['attempts']);
    }

    public function testSendsToAnotherApprovedUserOfTheOrganization(): void
    {
        $user = $this->member('caller@example.com');
        $other = $this->member('other@example.com', $user->organizationId);
        $id = $this->plainConnection($user);

        $response = $this->send($user, 'POST', self::path($id), ['to' => $other->email]);

        $this->assertSame(201, $response->status(), $response->body());
        $this->assertSame(['other@example.com'], $this->recipients());
    }

    public function testSendsToTheStoredAddressWhateverTheTypedCase(): void
    {
        $user = $this->member('caller@example.com');
        $this->member('Bob@Example.com', $user->organizationId);
        $id = $this->plainConnection($user);

        $response = $this->send($user, 'POST', self::path($id), ['to' => '  bob@EXAMPLE.com ']);

        $this->assertSame(201, $response->status(), $response->body());
        $this->assertSame(['Bob@Example.com'], $this->recipients());
    }

    public function testAnInactiveConnectionCanBeTested(): void
    {
        $user = $this->member('caller@example.com');
        $id = $this->plainConnection($user, active: false);

        $this->assertSame(201, $this->send($user, 'POST', self::path($id))->status());
    }

    public function testTheConnectionRowIsUnchanged(): void
    {
        $user = $this->member('caller@example.com');
        $id = $this->plainConnection($user);
        $before = $this->row('smtp_connections', 'id', $id);

        $this->send($user, 'POST', self::path($id));

        $this->assertSame($before, $this->row('smtp_connections', 'id', $id));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidRecipients(): array
    {
        return [
            'empty' => ['', 'to should not be empty'],
            'whitespace' => ['   ', 'to should not be empty'],
            'not a string' => [12, 'to must be a string'],
            'bad format' => ['not-an-email', 'to must be an email'],
            'too long' => [str_repeat('a', 250) . '@example.com', 'to must be shorter than or equal to 255 characters'],
            'line break' => ["bob@example.com\r\nBcc: x@example.com", 'to must not contain line breaks'],
        ];
    }

    #[DataProvider('invalidRecipients')]
    public function testAnInvalidToIsRefusedBeforeTheLookupAndTheLimit(mixed $to, string $message): void
    {
        $user = $this->member('caller@example.com');

        $response = $this->send($user, 'POST', self::path($this->plainConnection($user) + 999), ['to' => $to]);

        $this->assertSame(400, $response->status(), $response->body());
        $this->assertContains($message, self::json($response)['error']['details']);
        $this->assertNoAttempt($user);
    }

    public function testAnAddressThatIsNotAnApprovedOrganizationUserIsRefusedAlike(): void
    {
        $user = $this->member('caller@example.com');
        $id = $this->plainConnection($user);
        $this->member('pending@example.com', $user->organizationId, UserStatus::Pending);
        $this->member('denied@example.com', $user->organizationId, UserStatus::Denied);
        $this->member('foreign@example.com');

        foreach (['pending@example.com', 'denied@example.com', 'foreign@example.com', 'nobody@example.com'] as $to) {
            $response = $this->send($user, 'POST', self::path($id), ['to' => $to]);

            $this->assertValidationFailed($response, [self::NOT_A_USER]);
        }

        $this->assertNoAttempt($user);
        $this->assertSame([], $this->mailpit->messages());
        $this->assertSame([], $this->operatorLines());
    }

    public function testAnUnknownOrForeignConnectionIsNotFound(): void
    {
        $user = $this->member('caller@example.com');
        $foreign = $this->fixtures->smtpConnection(organizationId: $this->account()->organizationId, port: 2025);

        foreach ([$foreign, $foreign + 1000] as $id) {
            $response = $this->send($user, 'POST', self::path($id));

            $this->assertError($response, 404, 'NOT_FOUND', self::NOT_FOUND);
        }

        $this->assertNoAttempt($user);
        $this->assertSame([], $this->operatorLines());
    }

    public function testTheWindowRefusesWithRetryAfterAndDoesNotCountTheRefusal(): void
    {
        $user = $this->member('caller@example.com');
        $id = $this->plainConnection($user);

        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $this->assertSame(201, $this->send($user, 'POST', self::path($id))->status());
        }

        $response = $this->send($user, 'POST', self::path($id));

        $this->assertError($response, 429, 'TOO_MANY_REQUESTS', 'Too many test emails; try again later');
        $retry = $this->retryAfter($response);
        // Slack for the time the sends took: the window started at the first send.
        $this->assertGreaterThanOrEqual(3500, $retry);
        $this->assertLessThanOrEqual(3600, $retry);
        $this->assertSame(self::MAX_ATTEMPTS, (int) $this->limitRow($user)['attempts']);
        $this->assertCount(self::MAX_ATTEMPTS, $this->mailpit->messages());
        $this->assertCount(self::MAX_ATTEMPTS, $this->testLines());
    }

    public function testAHeldLeaseRefusesFirst(): void
    {
        $user = $this->member('caller@example.com');
        $id = $this->plainConnection($user);
        $this->fixtures->insert('smtp_test_limits', [
            'organization_id' => $user->organizationId,
            'window_started_at' => gmdate('Y-m-d H:i:s'),
            'attempts' => self::MAX_ATTEMPTS,
            'busy_until' => gmdate('Y-m-d H:i:s', time() + 20),
        ]);

        $response = $this->send($user, 'POST', self::path($id));

        $this->assertError($response, 429, 'TOO_MANY_REQUESTS', 'A test email is already in progress');
        $this->assertGreaterThanOrEqual(1, $this->retryAfter($response));
        $this->assertLessThanOrEqual(20, $this->retryAfter($response));
        $this->assertSame([], $this->mailpit->messages());
        $this->assertSame([], $this->operatorLines());
    }

    public function testAnExpiredLeaseIsIgnored(): void
    {
        $user = $this->member('caller@example.com');
        $id = $this->plainConnection($user);
        $this->fixtures->insert('smtp_test_limits', [
            'organization_id' => $user->organizationId,
            'window_started_at' => gmdate('Y-m-d H:i:s'),
            'attempts' => 0,
            'busy_until' => gmdate('Y-m-d H:i:s', time() - 5),
        ]);

        $this->assertSame(201, $this->send($user, 'POST', self::path($id))->status());
    }

    public function testTheLimitIsPerOrganization(): void
    {
        $user = $this->member('caller@example.com');
        $other = $this->member('other@example.com');
        $id = $this->plainConnection($user);
        $otherId = $this->plainConnection($other);

        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $this->send($user, 'POST', self::path($id));
        }

        $this->assertSame(429, $this->send($user, 'POST', self::path($id))->status());
        $this->assertSame(201, $this->send($other, 'POST', self::path($otherId))->status());
    }

    public function testSendTimeFailuresAreAnswered409AndCounted(): void
    {
        $user = $this->member('caller@example.com');
        $organizationId = $user->organizationId;
        $insecure = $this->connectionWithCredentials($organizationId, host: 'mailpit-plain', encryption: 'none');
        $unreadable = $this->connectionWithCredentials($organizationId, host: 'mailpit-plain');
        $this->setStoredPassword($unreadable, 'not-a-valid-ciphertext');

        $first = $this->send($user, 'POST', self::path($insecure));
        $second = $this->send($user, 'POST', self::path($unreadable));

        $this->assertSame(409, $first->status(), $first->body());
        $this->assertSame('SMTP_INSECURE_CREDENTIALS', self::json($first)['error']['code']);
        $this->assertSame(409, $second->status(), $second->body());
        $this->assertSame('SMTP_PASSWORD_UNREADABLE', self::json($second)['error']['code']);
        $this->assertSame(2, (int) $this->limitRow($user)['attempts']);
        $this->assertNull($this->limitRow($user)['busy_until']);
        $this->assertSame(
            ['SMTP_INSECURE_CREDENTIALS', 'SMTP_PASSWORD_UNREADABLE'],
            array_map(static fn (array $line): string => $line[1]['outcome'], $this->testLines()),
        );
    }

    public function testAHostThatIsNotAllowedIsAnswered409(): void
    {
        putenv('SMTP_ALLOW_PRIVATE_HOSTS=false');
        $user = $this->member('caller@example.com');
        $id = $this->fixtures->smtpConnection(organizationId: $user->organizationId, host: '127.0.0.1', port: 2025);

        $response = $this->send($user, 'POST', self::path($id));

        $this->assertError($response, 409, 'SMTP_HOST_NOT_ALLOWED');
        $this->assertSame(1, (int) $this->limitRow($user)['attempts']);
    }

    public function testASmtpFailureCarriesTheServerReplyAndReleasesTheLease(): void
    {
        $user = $this->member('caller@example.com');
        $starttls = Mailpit::starttls();
        $id = $this->connectionWithCredentials(
            $user->organizationId,
            username: Mailpit::AUTH_USER,
            password: 'wrong-' . self::$canary,
            host: $starttls->host(),
        );
        $this->pdo->prepare('UPDATE smtp_connections SET port = :port WHERE id = :id')
            ->execute(['port' => $starttls->smtpPort(), 'id' => $id]);

        $response = $this->send($user, 'POST', self::path($id));

        $this->assertError($response, 502, 'SMTP_AUTH_FAILED');
        $this->assertSame(535, self::json($response)['error']['smtp']['code']);
        $this->assertNull($this->limitRow($user)['busy_until']);
        $this->assertSame('SMTP_AUTH_FAILED', $this->testLines()[0][1]['outcome']);
    }

    public function testAnUnexpectedErrorReleasesTheLeaseAndWritesNoOperatorLine(): void
    {
        $user = $this->member('caller@example.com');
        $id = $this->plainConnection($user);
        $resolver = $this->createStub(HostResolver::class);
        $resolver->method('resolve')->willThrowException(new RuntimeException('resolver exploded'));

        $response = $this->sendWithHostPolicy($user, $id, new HostPolicy($resolver, true));

        $this->assertSame(500, $response->status());
        $this->assertNull($this->limitRow($user)['busy_until']);
        $this->assertSame([], $this->testLines());
    }

    public function testTheLogLinesCarryNoRecipientInTheOperatorLine(): void
    {
        $user = $this->member('caller@example.com');
        $id = $this->plainConnection($user);

        $this->send($user, 'POST', self::path($id));

        $messages = array_map(static fn (array $line): string => $line[0], $this->operatorLines());
        $this->assertSame(['smtp-send-attempt', 'smtp-connection-test'], $messages);
        $lines = $this->operatorLines();
        $this->assertSame(
            [
                'userId' => $user->id,
                'organizationId' => $user->organizationId,
                'connectionId' => $id,
                'outcome' => 'sent',
            ],
            $lines[1][1],
        );
        $this->assertStringContainsString('caller@example.com', (string) json_encode($lines[0][1]));
        $this->assertStringNotContainsString('caller@example.com', (string) json_encode($lines[1][1]));
    }

    public function testAnAnonymousCallerIsUnauthorized(): void
    {
        $this->assertSame(401, $this->send(null, 'POST', self::path(1))->status());
    }

    private function member(
        string $email,
        ?int $organizationId = null,
        UserStatus $status = UserStatus::Approved,
    ): User {
        $id = $this->fixtures->user(organizationId: $organizationId, email: $email, status: $status->value);

        return (new UserRepository($this->pdo))->findById($id) ?? self::fail('The fixture user is missing.');
    }

    private function plainConnection(User $user, bool $active = true): int
    {
        return $this->fixtures->smtpConnection(
            organizationId: $user->organizationId,
            host: $this->mailpit->host(),
            port: $this->mailpit->smtpPort(),
            active: $active,
        );
    }

    private static function path(int $id): string
    {
        return "/smtp-connections/{$id}/test.json";
    }

    /**
     * @return list<string> The `To` addresses of the delivered messages, oldest first.
     */
    private function recipients(): array
    {
        $addresses = [];

        foreach (array_reverse($this->mailpit->messages()) as $summary) {
            $message = $this->mailpit->message((string) $summary['ID']);
            $addresses[] = (string) $message['To'][0]['Address'];
        }

        return $addresses;
    }

    /**
     * @return array<string, string|null>
     */
    private function limitRow(User $user): array
    {
        return $this->row('smtp_test_limits', 'organization_id', $user->organizationId);
    }

    private function assertNoAttempt(User $user): void
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM smtp_test_limits WHERE organization_id = :id');
        $statement->execute(['id' => $user->organizationId]);

        $this->assertSame(0, (int) $statement->fetchColumn());
        $this->assertSame([], $this->testLines());
        $this->assertSame([], $this->mailpit->messages());
    }

    /**
     * @return list<array{string, array<string, mixed>}> The `smtp-connection-test` lines.
     */
    private function testLines(): array
    {
        return array_values(array_filter(
            $this->operatorLines(),
            static fn (array $line): bool => $line[0] === 'smtp-connection-test',
        ));
    }

    private function retryAfter(Response $response): int
    {
        foreach ($response->headers() as $line) {
            if (str_starts_with($line, 'Retry-After: ')) {
                return (int) substr($line, strlen('Retry-After: '));
            }
        }

        $this->fail('No Retry-After header.');
    }

    private function sendWithHostPolicy(User $user, int $id, HostPolicy $policy): Response
    {
        $logger = $this->log->logger();
        $controller = new FrontController(
            $this->router,
            new ErrorFormatter(logger: $this->formatterLog->logger()),
            static fn (): Container => new Container(new PdoFactory(), logger: $logger, hostPolicy: $policy),
        );

        return $controller->handle(new Request([
            'method' => 'POST',
            'uri' => self::path($id),
            'headers' => ['Content-Type' => 'application/json'],
            'cookies' => $this->accessCookie($user),
            'body' => '{}',
        ]));
    }
}
