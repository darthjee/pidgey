<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pidgey\Auth\Gate;
use Pidgey\Http\Handler\SmtpConnectionHandler;
use Pidgey\Http\OperatorLog;
use Pidgey\Http\PathId;
use Pidgey\Http\SmtpConnectionChange;
use Pidgey\Http\SmtpConnectionPayload;
use Pidgey\Http\SmtpConnectionRules;
use Pidgey\Repository\SmtpConnectionRepository;
use Pidgey\Tests\Support\SmtpConnectionEndpointTestCase;

/**
 * `GET`, `PATCH` and `DELETE /smtp-connections/:id.json` through the real routes.
 */
#[CoversClass(SmtpConnectionHandler::class)]
#[CoversClass(PathId::class)]
#[CoversClass(SmtpConnectionChange::class)]
#[CoversClass(SmtpConnectionPayload::class)]
#[CoversClass(SmtpConnectionRules::class)]
#[CoversClass(SmtpConnectionRepository::class)]
final class SmtpConnectionHandlerTest extends SmtpConnectionEndpointTestCase
{
    private const NOT_FOUND = 'SMTP connection not found';

    public function testReadsAConnection(): void
    {
        $user = $this->account();
        $id = $this->connectionWithCredentials($user->organizationId, active: true);

        $response = $this->send($user, 'GET', self::path($id));

        $this->assertSame(200, $response->status());
        $item = self::item($response);
        $this->assertSame($id, $item['id']);
        $this->assertSame('mailer', $item['username']);
        $this->assertTrue($item['hasPassword']);
        $this->assertTrue($item['active']);
        $this->assertArrayNotHasKey('password', $item);
        $this->assertSame([], $this->operatorLines());
    }

    public function testAnUndecryptablePasswordStillReadsAsSet(): void
    {
        $user = $this->account();
        $id = $this->connectionWithCredentials($user->organizationId);
        $this->setStoredPassword($id, random_bytes(48));

        $response = $this->send($user, 'GET', self::path($id));

        $this->assertSame(200, $response->status());
        $this->assertTrue(self::item($response)['hasPassword']);
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function routes(): array
    {
        return [
            'read' => ['GET', []],
            'update' => ['PATCH', ['name' => 'renamed']],
            'delete' => ['DELETE', []],
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('routes')]
    public function testAnUnknownIdAnswers404(string $method, array $body): void
    {
        $user = $this->account();
        $id = $this->fixtures->smtpConnection(organizationId: $user->organizationId);

        $ids = [(string) ($id + 1000), '9223372036854775807', '9223372036854775808', '99999999999999999999'];

        foreach ($ids as $raw) {
            $response = $this->send($user, $method, "/smtp-connections/{$raw}.json", $body);

            $this->assertError($response, 404, 'NOT_FOUND', self::NOT_FOUND);
        }

        $this->assertSame(1, $this->rowCount('smtp_connections'));
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('routes')]
    public function testAnotherOrganizationsConnectionAnswers404(string $method, array $body): void
    {
        $foreign = $this->connectionWithCredentials($this->account()->organizationId);
        $before = $this->row('smtp_connections', 'id', $foreign);

        $response = $this->send($this->account(), $method, self::path($foreign), $body);

        $this->assertError($response, 404, 'NOT_FOUND', self::NOT_FOUND);
        $this->assertSame($before, $this->row('smtp_connections', 'id', $foreign));
        $this->assertSame([], $this->operatorLines());
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('routes')]
    public function testAMalformedIdAnswers400BeforeValidation(string $method, array $body): void
    {
        $user = $this->account();

        foreach (['abc', '0', '01', '-1', '1.5', '1e3'] as $raw) {
            $response = $this->send($user, $method, "/smtp-connections/{$raw}.json", ['port' => 'invalid', ...$body]);

            $this->assertError($response, 400, 'BAD_REQUEST', PathId::MALFORMED);
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('routes')]
    public function testTheGateAnswers401BeforeTheIdAndTheBody(string $method, array $body): void
    {
        $id = $this->connectionWithCredentials($this->account()->organizationId);

        foreach ([(string) $id, 'abc', '99999999999999999999'] as $raw) {
            $response = $this->send(null, $method, "/smtp-connections/{$raw}.json", ['port' => 'invalid', ...$body]);

            $this->assertError($response, 401, 'UNAUTHORIZED', Gate::UNAUTHORIZED_MESSAGE);
        }

        $this->assertSame(1, $this->rowCount('smtp_connections'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function writeMethods(): array
    {
        return ['update' => ['PATCH'], 'delete' => ['DELETE']];
    }

    #[DataProvider('writeMethods')]
    public function testACrossSiteWriteIsRejected(string $method): void
    {
        $user = $this->account();
        $id = $this->fixtures->smtpConnection(organizationId: $user->organizationId);
        $before = $this->row('smtp_connections', 'id', $id);

        $response = $this->send(
            $user,
            $method,
            self::path($id),
            ['name' => 'renamed'],
            ['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'https://evil.example'],
        );

        $this->assertError($response, 403, 'FORBIDDEN', 'Cross-site request rejected');
        $this->assertSame($before, $this->row('smtp_connections', 'id', $id));
    }

    public function testUpdatesTheNameAndKeepsTheStoredPasswordBytes(): void
    {
        $user = $this->account();
        $id = $this->connectionWithCredentials($user->organizationId, active: true);
        $stored = $this->storedPassword($id);

        $response = $this->send($user, 'PATCH', self::path($id), ['name' => '  Renamed ']);

        $this->assertSame(200, $response->status(), $response->body());
        $item = self::item($response);
        $this->assertSame('Renamed', $item['name']);
        $this->assertTrue($item['hasPassword']);
        $this->assertTrue($item['active']);
        $this->assertSame($stored, $this->storedPassword($id));
        $this->assertOperatorLogged(OperatorLog::SMTP_CONNECTION_UPDATE, $user, $id, ['name']);
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function changesWithoutPassword(): array
    {
        return [
            'fromAddress' => [['fromAddress' => 'relay@example.com'], ['fromAddress']],
            'username' => [['username' => 'other-user'], ['username']],
            'name and username' => [['name' => 'Renamed', 'username' => 'other-user'], ['name', 'username']],
            'the stored host again' => [['host' => ' smtp.example.com '], []],
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string>         $fields
     */
    #[DataProvider('changesWithoutPassword')]
    public function testChangesThatDoNotRedirectTheCredentialsNeedNoPassword(array $body, array $fields): void
    {
        $user = $this->account();
        $id = $this->connectionWithCredentials($user->organizationId);
        $stored = $this->storedPassword($id);

        $response = $this->send($user, 'PATCH', self::path($id), $body);

        $this->assertSame(200, $response->status(), $response->body());
        $this->assertSame($stored, $this->storedPassword($id));

        if ($fields === []) {
            $this->assertSame([], $this->operatorLines());
        } else {
            $this->assertOperatorLogged(OperatorLog::SMTP_CONNECTION_UPDATE, $user, $id, $fields);
        }
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function redirections(): array
    {
        return [
            'host' => [['host' => 'smtp.elsewhere.example'], [SmtpConnectionRules::PASSWORD_REQUIRED]],
            'host case' => [['host' => 'SMTP.example.com'], [SmtpConnectionRules::PASSWORD_REQUIRED]],
            'port' => [['port' => 2525], [SmtpConnectionRules::PASSWORD_REQUIRED]],
            'encryption' => [['encryption' => 'ssl'], [SmtpConnectionRules::PASSWORD_REQUIRED]],
            'encryption none' => [
                ['encryption' => 'none'],
                [SmtpConnectionRules::ENCRYPTION_REQUIRED, SmtpConnectionRules::PASSWORD_REQUIRED],
            ],
            'host with an empty password' => [
                ['host' => 'smtp.elsewhere.example', 'password' => ''],
                [SmtpConnectionRules::PAIRING, SmtpConnectionRules::PASSWORD_REQUIRED],
            ],
            'host and username' => [
                ['host' => 'smtp.elsewhere.example', 'username' => 'other-user'],
                [SmtpConnectionRules::PASSWORD_REQUIRED],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string>         $details
     */
    #[DataProvider('redirections')]
    public function testRedirectingTheCredentialsRequiresThePassword(array $body, array $details): void
    {
        $user = $this->account();
        $id = $this->connectionWithCredentials($user->organizationId);
        $before = $this->row('smtp_connections', 'id', $id);

        $response = $this->send($user, 'PATCH', self::path($id), $body);

        $this->assertValidationFailed($response, $details);
        $this->assertSame($before, $this->row('smtp_connections', 'id', $id));
        $this->assertSame([], $this->operatorLines());
    }

    public function testRedirectingWithANewPasswordReplacesTheStoredBytes(): void
    {
        $user = $this->account();
        $id = $this->connectionWithCredentials($user->organizationId, password: self::randomPassword('old-'));
        $stored = $this->storedPassword($id);

        $response = $this->send($user, 'PATCH', self::path($id), [
            'host' => 'smtp.elsewhere.example',
            'port' => 465,
            'encryption' => 'ssl',
            'password' => self::$canary,
        ]);

        $this->assertSame(200, $response->status(), $response->body());
        $item = self::item($response);
        $this->assertSame(['smtp.elsewhere.example', 465, 'ssl', true], [$item['host'], $item['port'],
            $item['encryption'], $item['hasPassword']]);
        $newStored = (string) $this->storedPassword($id);
        $this->assertNotSame($stored, $newStored);
        $this->assertSame(self::$canary, self::cipher()->decrypt($newStored, $user->organizationId, $id));
        $this->assertOperatorLogged(
            OperatorLog::SMTP_CONNECTION_UPDATE,
            $user,
            $id,
            ['host', 'port', 'encryption', 'password'],
        );
    }

    public function testRedirectingWhileClearingBothCredentialsIsAllowed(): void
    {
        $user = $this->account();
        $id = $this->connectionWithCredentials($user->organizationId);

        $response = $this->send($user, 'PATCH', self::path($id), [
            'host' => 'mailpit',
            'port' => 1025,
            'encryption' => 'none',
            'username' => '',
            'password' => '',
        ]);

        $this->assertSame(200, $response->status(), $response->body());
        $item = self::item($response);
        $this->assertNull($item['username']);
        $this->assertFalse($item['hasPassword']);
        $this->assertNull($this->storedPassword($id));
        $this->assertOperatorLogged(
            OperatorLog::SMTP_CONNECTION_UPDATE,
            $user,
            $id,
            ['host', 'port', 'encryption', 'username', 'password'],
        );
    }

    public function testOnlyAPasswordRotatesIt(): void
    {
        $user = $this->account();
        $id = $this->connectionWithCredentials($user->organizationId, password: self::randomPassword('old-'));
        $stored = $this->storedPassword($id);

        $response = $this->send($user, 'PATCH', self::path($id), ['password' => self::$canary]);

        $this->assertSame(200, $response->status(), $response->body());
        $newStored = (string) $this->storedPassword($id);
        $this->assertNotSame($stored, $newStored);
        $this->assertSame(self::$canary, self::cipher()->decrypt($newStored, $user->organizationId, $id));
        $this->assertOperatorLogged(OperatorLog::SMTP_CONNECTION_UPDATE, $user, $id, ['password']);
    }

    public function testChangingTheHostWithoutCredentialsIsAllowed(): void
    {
        $user = $this->account();
        $id = $this->fixtures->smtpConnection(organizationId: $user->organizationId);

        $response = $this->send($user, 'PATCH', self::path($id), ['host' => 'smtp.example.com', 'port' => 25]);

        $this->assertSame(200, $response->status(), $response->body());
        $this->assertSame('smtp.example.com', self::item($response)['host']);
        $this->assertOperatorLogged(OperatorLog::SMTP_CONNECTION_UPDATE, $user, $id, ['host', 'port']);
    }

    public function testAddingCredentialsWithTheEncryptionIsAllowed(): void
    {
        $user = $this->account();
        $id = $this->fixtures->smtpConnection(organizationId: $user->organizationId);

        $response = $this->send($user, 'PATCH', self::path($id), [
            'encryption' => 'tls',
            'username' => 'mailer',
            'password' => self::$canary,
        ]);

        $this->assertSame(200, $response->status(), $response->body());
        $this->assertTrue(self::item($response)['hasPassword']);
        $stored = (string) $this->storedPassword($id);
        $this->assertSame(self::$canary, self::cipher()->decrypt($stored, $user->organizationId, $id));
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function invalidMergedStates(): array
    {
        return [
            'clearing the username only' => [['username' => ''], [SmtpConnectionRules::PAIRING]],
            'clearing the password only' => [['password' => ''], [SmtpConnectionRules::PAIRING]],
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string>         $details
     */
    #[DataProvider('invalidMergedStates')]
    public function testThePairingIsCheckedOnTheMergedState(array $body, array $details): void
    {
        $user = $this->account();
        $id = $this->connectionWithCredentials($user->organizationId);

        $response = $this->send($user, 'PATCH', self::path($id), $body);

        $this->assertValidationFailed($response, $details);
        $this->assertNotNull($this->storedPassword($id));
    }

    public function testAddingCredentialsWithoutEncryptionIsRejected(): void
    {
        $user = $this->account();
        $id = $this->fixtures->smtpConnection(organizationId: $user->organizationId);

        $response = $this->send($user, 'PATCH', self::path($id), [
            'username' => 'mailer',
            'password' => self::$canary,
        ]);

        $this->assertValidationFailed($response, [SmtpConnectionRules::ENCRYPTION_REQUIRED]);
        $this->assertNull($this->storedPassword($id));
    }

    public function testARowStoredBeforeTheRulesCannotBePatchedUntilFixed(): void
    {
        $user = $this->account();
        $id = $this->connectionWithCredentials($user->organizationId, encryption: 'none');

        $rename = $this->send($user, 'PATCH', self::path($id), ['name' => 'Renamed']);
        $read = $this->send($user, 'GET', self::path($id));

        $this->assertValidationFailed($rename, [SmtpConnectionRules::ENCRYPTION_REQUIRED]);
        $this->assertSame(200, $read->status());
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function invalidUpdates(): array
    {
        return [
            'clearing the required fields' => [
                ['name' => '', 'host' => ' ', 'encryption' => '', 'fromAddress' => ''],
                [
                    'name should not be empty',
                    'host should not be empty',
                    'encryption should not be empty',
                    'fromAddress should not be empty',
                ],
            ],
            'port as a string' => [['port' => '587'], ['port must be an integer']],
            'port out of range' => [['port' => 65536], ['port must be between 1 and 65535']],
            'unknown encryption' => [
                ['encryption' => 'starttls'],
                ['encryption must be one of the following values: none, tls, ssl'],
            ],
            'invalid host' => [['host' => 'smtp.example.com.'], ['host must be a hostname or an IP address']],
            'fromAddress with a line break' => [
                ['fromAddress' => "a@example.com\nBcc: b@example.com"],
                ['fromAddress must be an email', 'fromAddress must not contain line breaks'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string>         $details
     */
    #[DataProvider('invalidUpdates')]
    public function testFieldRulesApplyToTheUpdate(array $body, array $details): void
    {
        $user = $this->account();
        $id = $this->fixtures->smtpConnection(organizationId: $user->organizationId);
        $before = $this->row('smtp_connections', 'id', $id);

        $response = $this->send($user, 'PATCH', self::path($id), $body);

        $this->assertValidationFailed($response, $details);
        $this->assertSame($before, $this->row('smtp_connections', 'id', $id));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function noOpBodies(): array
    {
        return [
            'empty body' => [[]],
            'every field null' => [['name' => null, 'host' => null, 'port' => null, 'encryption' => null,
                'username' => null, 'password' => null, 'fromAddress' => null]],
            'only active' => [['active' => false]],
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('noOpBodies')]
    public function testANoOpUpdateAnswers200WithoutALogLine(array $body): void
    {
        $user = $this->account();
        $id = $this->connectionWithCredentials($user->organizationId, active: true);
        $before = $this->row('smtp_connections', 'id', $id);

        $response = $this->send($user, 'PATCH', self::path($id), $body);

        $this->assertSame(200, $response->status(), $response->body());
        $this->assertTrue(self::item($response)['active']);
        $this->assertSame($before, $this->row('smtp_connections', 'id', $id));
        $this->assertSame([], $this->operatorLines());
    }

    public function testActiveInAnUpdateIsIgnored(): void
    {
        $user = $this->account();
        $id = $this->fixtures->smtpConnection(organizationId: $user->organizationId);

        $response = $this->send($user, 'PATCH', self::path($id), ['name' => 'Renamed', 'active' => true]);

        $this->assertSame(200, $response->status(), $response->body());
        $this->assertFalse(self::item($response)['active']);
        $this->assertSame([], $this->activeIds($user->organizationId));
        $this->assertOperatorLogged(OperatorLog::SMTP_CONNECTION_UPDATE, $user, $id, ['name']);
    }

    public function testDeletesAConnection(): void
    {
        $user = $this->account();
        $id = $this->fixtures->smtpConnection(organizationId: $user->organizationId);
        $kept = $this->fixtures->smtpConnection(organizationId: $user->organizationId);

        $response = $this->send($user, 'DELETE', self::path($id));

        $this->assertSame(204, $response->status());
        $this->assertSame('', $response->body());
        $this->assertSame(1, $this->rowCount('smtp_connections'));
        $this->assertSame(200, $this->send($user, 'GET', self::path($kept))->status());
        $this->assertOperatorLogged(OperatorLog::SMTP_CONNECTION_DELETE, $user, $id);
    }

    public function testDeletingTheActiveConnectionLeavesNoneActive(): void
    {
        $user = $this->account();
        $id = $this->connectionWithCredentials($user->organizationId, active: true);
        $this->fixtures->smtpConnection(organizationId: $user->organizationId);

        $response = $this->send($user, 'DELETE', self::path($id));
        $again = $this->send($user, 'DELETE', self::path($id));

        $this->assertSame(204, $response->status());
        $this->assertSame([], $this->activeIds($user->organizationId));
        $this->assertError($again, 404, 'NOT_FOUND', self::NOT_FOUND);
        $this->assertOperatorLogged(OperatorLog::SMTP_CONNECTION_DELETE, $user, $id);
    }

    private static function path(int $id): string
    {
        return "/smtp-connections/{$id}.json";
    }
}
