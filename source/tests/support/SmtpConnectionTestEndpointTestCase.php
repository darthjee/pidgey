<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Pidgey\Container;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\FrontController;
use Pidgey\Http\Request;
use Pidgey\Http\Response;
use Pidgey\Infra\PdoFactory;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\UserRepository;
use Pidgey\Smtp\HostPolicy;

abstract class SmtpConnectionTestEndpointTestCase extends SmtpConnectionEndpointTestCase
{
    protected const NOT_FOUND = 'SMTP connection not found';

    protected const NOT_A_USER = 'to must be the email of a user of the organization';

    protected const MAX_ATTEMPTS = 3;

    protected const VARIABLES = ['SMTP_ALLOW_PRIVATE_HOSTS', 'SMTP_TEST_MAX_ATTEMPTS', 'SMTP_TEST_WINDOW'];

    /** @var array<string, string|false> */
    protected array $savedSettings = [];

    protected Mailpit $mailpit;

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

    protected function member(
        string $email,
        ?int $organizationId = null,
        UserStatus $status = UserStatus::Approved,
    ): User {
        $id = $this->fixtures->user(organizationId: $organizationId, email: $email, status: $status->value);

        return (new UserRepository($this->pdo))->findById($id) ?? self::fail('The fixture user is missing.');
    }

    protected function plainConnection(User $user, bool $active = true): int
    {
        return $this->fixtures->smtpConnection(
            organizationId: $user->organizationId,
            host: $this->mailpit->host(),
            port: $this->mailpit->smtpPort(),
            active: $active,
        );
    }

    protected static function path(int $id): string
    {
        return "/smtp-connections/{$id}/test.json";
    }

    /**
     * @return list<string> The `To` addresses of the delivered messages, oldest first.
     */
    protected function recipients(): array
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
    protected function limitRow(User $user): array
    {
        return $this->row('smtp_test_limits', 'organization_id', $user->organizationId);
    }

    protected function assertNoAttempt(User $user): void
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
    protected function testLines(): array
    {
        return array_values(array_filter(
            $this->operatorLines(),
            static fn (array $line): bool => $line[0] === 'smtp-connection-test',
        ));
    }

    protected function retryAfter(Response $response): int
    {
        foreach ($response->headers() as $line) {
            if (str_starts_with($line, 'Retry-After: ')) {
                return (int) substr($line, strlen('Retry-After: '));
            }
        }

        $this->fail('No Retry-After header.');
    }

    protected function sendWithHostPolicy(User $user, int $id, HostPolicy $policy): Response
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
