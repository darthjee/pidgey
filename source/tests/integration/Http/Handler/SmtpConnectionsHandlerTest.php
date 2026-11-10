<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http\Handler;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pidgey\Auth\Gate;
use Pidgey\Http\Handler\SmtpConnectionsHandler;
use Pidgey\Http\OperatorLog;
use Pidgey\Http\SmtpConnectionPayload;
use Pidgey\Http\SmtpConnectionRules;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\PdoFactory;
use Pidgey\Repository\SmtpConnectionRepository;
use Pidgey\Tests\Support\SmtpConnectionEndpointTestCase;
use RuntimeException;

/**
 * `GET` and `POST /smtp-connections.json` through the real routes.
 */
#[CoversClass(SmtpConnectionsHandler::class)]
#[CoversClass(SmtpConnectionPayload::class)]
#[CoversClass(SmtpConnectionRules::class)]
#[CoversClass(SmtpConnectionRepository::class)]
final class SmtpConnectionsHandlerTest extends SmtpConnectionEndpointTestCase
{
    private const PATH = '/smtp-connections.json';

    private const ITEM_KEYS = ['id', 'name', 'host', 'port', 'encryption', 'username', 'hasPassword', 'fromAddress',
        'active', 'createdAt', 'updatedAt'];

    private const TIMESTAMP = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/';

    public function testAnEmptyListAnswersAnEmptyArray(): void
    {
        $response = $this->send($this->account(), 'GET', self::PATH);

        $this->assertSame(200, $response->status());
        $this->assertSame('{"smtpConnections":[]}', $response->body());
    }

    public function testListsTheOrganizationsConnectionsInIdOrder(): void
    {
        $user = $this->account();
        $organizationId = $user->organizationId;
        $first = $this->fixtures->smtpConnection(organizationId: $organizationId, name: 'Mailpit', active: true);
        $this->fixtures->smtpConnection(organizationId: $this->account()->organizationId, name: 'Foreign');
        $second = $this->connectionWithCredentials($organizationId);
        $undecryptable = $this->fixtures->smtpConnection(
            organizationId: $organizationId,
            encryption: 'ssl',
            username: 'mailer',
            password: random_bytes(48),
        );

        $response = $this->send($user, 'GET', self::PATH);

        $this->assertSame(200, $response->status());
        $items = self::json($response)['smtpConnections'];
        $this->assertSame([$first, $second, $undecryptable], array_column($items, 'id'));
        $this->assertSame([false, true, true], array_column($items, 'hasPassword'));
        $this->assertSame([true, false, false], array_column($items, 'active'));
        $this->assertSame([null, 'mailer', 'mailer'], array_column($items, 'username'));

        foreach ($items as $item) {
            $this->assertSame(self::ITEM_KEYS, array_keys($item));
            $this->assertMatchesRegularExpression(self::TIMESTAMP, $item['createdAt']);
            $this->assertMatchesRegularExpression(self::TIMESTAMP, $item['updatedAt']);
        }

        $this->assertSame([], $this->operatorLines());
    }

    public function testCreatesAConnectionWithoutCredentials(): void
    {
        $user = $this->account();

        $response = $this->send($user, 'POST', self::PATH, [
            'name' => '  Mailpit ',
            'host' => ' mailpit ',
            'port' => 1025,
            'encryption' => ' none ',
            'username' => '',
            'password' => '',
            'fromAddress' => ' noreply@example.com ',
        ]);

        $this->assertSame(201, $response->status(), $response->body());
        $item = self::item($response);
        $this->assertSame(self::ITEM_KEYS, array_keys($item));
        $this->assertSame(
            ['Mailpit', 'mailpit', 1025, 'none', null, false, 'noreply@example.com', false],
            [$item['name'], $item['host'], $item['port'], $item['encryption'], $item['username'],
                $item['hasPassword'], $item['fromAddress'], $item['active']],
        );
        $this->assertMatchesRegularExpression(self::TIMESTAMP, $item['createdAt']);
        $row = $this->row('smtp_connections', 'id', $item['id']);
        $this->assertSame($user->organizationId, (int) $row['organization_id']);
        $this->assertNull($row['username']);
        $this->assertNull($row['password']);
        $this->assertOperatorLogged(OperatorLog::SMTP_CONNECTION_CREATE, $user, $item['id']);
    }

    public function testCreatesAConnectionWithCredentialsAlwaysInactive(): void
    {
        $user = $this->account();
        // The password is never trimmed.
        $password = ' ' . self::$canary . ' ';

        $response = $this->send($user, 'POST', self::PATH, [
            'name' => 'Office relay',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => ' relay-user ',
            'password' => $password,
            'fromAddress' => 'noreply@example.com',
            'active' => true,
        ]);

        $this->assertSame(201, $response->status(), $response->body());
        $item = self::item($response);
        $this->assertSame('relay-user', $item['username']);
        $this->assertTrue($item['hasPassword']);
        $this->assertFalse($item['active']);
        $this->assertSame([], $this->activeIds($user->organizationId));
        $stored = (string) $this->storedPassword($item['id']);
        $this->assertSame($password, self::cipher()->decrypt($stored, $user->organizationId, $item['id']));
        $this->assertOperatorLogged(OperatorLog::SMTP_CONNECTION_CREATE, $user, $item['id']);
    }

    public function testAnUnusualPortEncryptionPairIsAccepted(): void
    {
        $body = self::body(['port' => 587, 'encryption' => 'ssl']);

        $response = $this->send($this->account(), 'POST', self::PATH, $body);

        $this->assertSame(201, $response->status(), $response->body());
    }

    public function testAPunycodeHostAndIpLiteralsAreAccepted(): void
    {
        $user = $this->account();

        foreach (['smtp.xn--exmple-cua.de', '192.0.2.10', '[2001:db8::1]', '2001:db8::1'] as $host) {
            $response = $this->send($user, 'POST', self::PATH, self::body(['host' => $host]));

            $this->assertSame(201, $response->status(), $response->body());
            $this->assertSame($host, self::item($response)['host']);
        }
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function invalidBodies(): array
    {
        $long = str_repeat('a', 256);

        return [
            'empty body' => [[], [
                'name should not be empty',
                'host should not be empty',
                'port should not be empty',
                'encryption should not be empty',
                'fromAddress should not be empty',
            ]],
            'blank strings' => [
                self::body(['name' => '  ', 'host' => ' ', 'encryption' => '', 'fromAddress' => ' ']),
                [
                    'name should not be empty',
                    'host should not be empty',
                    'encryption should not be empty',
                    'fromAddress should not be empty',
                ],
            ],
            'wrong types' => [
                self::body(['name' => 1, 'host' => true, 'encryption' => [], 'username' => 5, 'password' => 5,
                    'fromAddress' => false]),
                [
                    'name must be a string',
                    'host must be a string',
                    'encryption must be a string',
                    'username must be a string',
                    'password must be a string',
                    'fromAddress must be a string',
                ],
            ],
            'too long' => [
                self::body(['name' => $long, 'username' => $long, 'password' => $long]),
                [
                    'name must be shorter than or equal to 255 characters',
                    'username must be shorter than or equal to 255 characters',
                    'password must be shorter than or equal to 255 characters',
                ],
            ],
            'port as a string' => [self::body(['port' => '587']), ['port must be an integer']],
            'port as a float' => [self::body(['port' => 587.5]), ['port must be an integer']],
            'port as a float with no fraction' => ['{"name":"n","host":"mailpit","port":587.0,'
                . '"encryption":"none","fromAddress":"noreply@example.com"}', ['port must be an integer']],
            'port as a boolean' => [self::body(['port' => true]), ['port must be an integer']],
            'port 0' => [self::body(['port' => 0]), ['port must be between 1 and 65535']],
            'port 65536' => [self::body(['port' => 65536]), ['port must be between 1 and 65535']],
            'unknown encryption' => [
                self::body(['encryption' => 'starttls']),
                ['encryption must be one of the following values: none, tls, ssl'],
            ],
            'case-changed encryption' => [
                self::body(['encryption' => 'TLS']),
                ['encryption must be one of the following values: none, tls, ssl'],
            ],
            'invalid email' => [self::body(['fromAddress' => 'not-an-email']), ['fromAddress must be an email']],
            'fromAddress with a line break' => [
                self::body(['fromAddress' => "noreply@example.com\r\nBcc: x@example.com"]),
                ['fromAddress must be an email', 'fromAddress must not contain line breaks'],
            ],
            'username with a control character' => [
                self::body(['username' => "mail\x07er", 'password' => 'secret', 'encryption' => 'tls']),
                ['username must not contain control characters'],
            ],
            'username with a line break' => [
                self::body(['username' => "mail\r\ner", 'password' => 'secret', 'encryption' => 'tls']),
                ['username must not contain control characters'],
            ],
            'password with a line break' => [
                self::body(['username' => 'mailer', 'password' => "sec\nret", 'encryption' => 'tls']),
                ['password must not contain line breaks'],
            ],
            'password with a NUL byte' => [
                self::body(['username' => 'mailer', 'password' => "sec\0ret", 'encryption' => 'tls']),
                ['password must not contain a NUL byte'],
            ],
            'fromAddress with U+0001 in a quoted local part' => [
                self::body(['fromAddress' => "\"a\x01b\"@example.com"]),
                ['fromAddress must not contain control characters'],
            ],
            'fromAddress with NUL in a quoted local part' => [
                self::body(['fromAddress' => "\"a\0b\"@example.com"]),
                ['fromAddress must be an email', 'fromAddress must not contain control characters'],
            ],
        ];
    }

    /**
     * @param array<string, mixed>|string $body
     * @param list<string>                $details
     */
    #[DataProvider('invalidBodies')]
    public function testFieldRulesAnswer400WithEveryMessage(array|string $body, array $details): void
    {
        $user = $this->account();

        $response = $this->send($user, 'POST', self::PATH, $body);

        $this->assertValidationFailed($response, $details);
        $this->assertSame(0, $this->rowCount('smtp_connections'));
        $this->assertSame([], $this->operatorLines());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidHosts(): array
    {
        return [
            'space' => ['smtp example.com'],
            'line break' => ["smtp.example.com\r\nRCPT TO:<x@example.com>"],
            'control character' => ["smtp.exa\x01mple.com"],
            'unicode' => ['smtp.exämple.de'],
            'label over 63 characters' => [str_repeat('a', 64) . '.example.com'],
            'leading hyphen' => ['-smtp.example.com'],
            'trailing hyphen' => ['smtp-.example.com'],
            'over 253 characters' => [implode('.', [...array_fill(0, 3, str_repeat('a', 63)), str_repeat('b', 62)])],
            'trailing dot' => ['smtp.example.com.'],
            'zone id' => ['fe80::1%eth0'],
            'all-numeric non-IPv4' => ['999.1.1.1'],
        ];
    }

    #[DataProvider('invalidHosts')]
    public function testAnInvalidHostIsRejected(string $host): void
    {
        $response = $this->send($this->account(), 'POST', self::PATH, self::body(['host' => $host]));

        $this->assertValidationFailed($response, ['host must be a hostname or an IP address']);
        $this->assertSame(0, $this->rowCount('smtp_connections'));
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function invalidCredentials(): array
    {
        return [
            'only a username' => [
                ['username' => 'mailer', 'encryption' => 'tls'],
                [SmtpConnectionRules::PAIRING],
            ],
            'only a password' => [
                ['password' => self::randomPassword(), 'encryption' => 'tls', 'username' => ''],
                [SmtpConnectionRules::PAIRING],
            ],
            'credentials without encryption' => [
                ['username' => 'mailer', 'password' => self::randomPassword(), 'encryption' => 'none'],
                [SmtpConnectionRules::ENCRYPTION_REQUIRED],
            ],
            'only a username without encryption' => [
                ['username' => 'mailer'],
                [SmtpConnectionRules::PAIRING, SmtpConnectionRules::ENCRYPTION_REQUIRED],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $credentials
     * @param list<string>         $details
     */
    #[DataProvider('invalidCredentials')]
    public function testCrossFieldRulesAnswer400(array $credentials, array $details): void
    {
        $response = $this->send($this->account(), 'POST', self::PATH, self::body($credentials));

        $this->assertValidationFailed($response, $details);
        $this->assertSame(0, $this->rowCount('smtp_connections'));
    }

    public function testTheGateAnswers401BeforeValidation(): void
    {
        $list = $this->send(null, 'GET', self::PATH);
        $create = $this->send(null, 'POST', self::PATH, ['port' => 'not a port']);

        $this->assertError($list, 401, 'UNAUTHORIZED', Gate::UNAUTHORIZED_MESSAGE);
        $this->assertError($create, 401, 'UNAUTHORIZED', Gate::UNAUTHORIZED_MESSAGE);
        $this->assertSame(0, $this->rowCount('smtp_connections'));
    }

    public function testACrossSiteCreateIsRejected(): void
    {
        $response = $this->send(
            $this->account(),
            'POST',
            self::PATH,
            self::body(),
            ['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'https://evil.example'],
        );

        $this->assertError($response, 403, 'FORBIDDEN', 'Cross-site request rejected');
        $this->assertSame(0, $this->rowCount('smtp_connections'));
    }

    public function testAnUnusableEncryptionKeyAnswers500AndLeavesNoRow(): void
    {
        putenv('SMTP_ENCRYPTION_KEY');

        $response = $this->send($this->account(), 'POST', self::PATH, self::credentialsBody());

        $this->assertError($response, 500, 'INTERNAL_ERROR');
        $this->assertSame(0, $this->rowCount('smtp_connections'));
        $this->assertSame([], $this->operatorLines());
    }

    public function testAFailureWhileStoringThePasswordRollsTheCreateBack(): void
    {
        $response = $this->send(
            $this->account(),
            'POST',
            self::PATH,
            self::credentialsBody(),
            [],
            static fn (): PDO => self::passwordWriteFailingPdo(),
        );

        $this->assertError($response, 500, 'INTERNAL_ERROR');
        $this->assertSame(0, $this->rowCount('smtp_connections'));
        $this->assertSame([], $this->operatorLines());
    }

    /**
     * A valid create body without credentials, with the given fields replaced.
     *
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private static function body(array $fields = []): array
    {
        return [
            'name' => 'Mailpit',
            'host' => 'mailpit',
            'port' => 1025,
            'encryption' => 'none',
            'fromAddress' => 'noreply@example.com',
            ...$fields,
        ];
    }

    /**
     * A valid create body with the canary password.
     *
     * @return array<string, mixed>
     */
    private static function credentialsBody(): array
    {
        return self::body([
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'mailer',
            'password' => self::$canary,
        ]);
    }

    /**
     * A real connection whose statement writing the encrypted password fails, after the insert.
     */
    private static function passwordWriteFailingPdo(): PDO
    {
        $config = DatabaseConfig::fromEnv();
        $factory = new PdoFactory();

        $dsn = $factory->dsn($config, true);

        return new class ($dsn, $config->user, $config->password, $factory->options()) extends PDO {
            /**
             * @param array<int, mixed> $options
             */
            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                if (str_starts_with($query, 'UPDATE smtp_connections SET password')) {
                    throw new RuntimeException('Simulated failure while storing the SMTP password');
                }

                return parent::prepare($query, $options);
            }
        };
    }
}
