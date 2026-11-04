<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Auth;

use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Auth\AccessToken;
use Pidgey\Auth\Passwords;
use Pidgey\Auth\RefreshTokenValue;
use Pidgey\Auth\Registration;
use Pidgey\Auth\SecretKeys;
use Pidgey\Auth\SessionRotation;
use Pidgey\Auth\Sessions;
use Pidgey\Auth\SessionTokens;
use Pidgey\Auth\SessionUuid;
use Pidgey\Container;
use Pidgey\Http\ConflictException;
use Pidgey\Http\HttpException;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Infra\SystemWallClock;
use Pidgey\Infra\Transaction;
use Pidgey\Infra\UserApproval;
use Pidgey\Model\Organization;
use Pidgey\Model\Row;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\OrganizationRepository;
use Pidgey\Repository\RefreshTokenRepository;
use Pidgey\Repository\Sql;
use Pidgey\Repository\UserRepository;
use Pidgey\Tests\Support\Fixtures;
use Pidgey\Tests\Support\SessionEndpointTestCase;

#[CoversClass(Registration::class)]
#[UsesClass(AccessToken::class)]
#[UsesClass(ConflictException::class)]
#[UsesClass(Container::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(Organization::class)]
#[UsesClass(OrganizationRepository::class)]
#[UsesClass(Passwords::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(RefreshTokenRepository::class)]
#[UsesClass(RefreshTokenValue::class)]
#[UsesClass(Row::class)]
#[UsesClass(SecretKeys::class)]
#[UsesClass(SessionRotation::class)]
#[UsesClass(Sessions::class)]
#[UsesClass(SessionTokens::class)]
#[UsesClass(SessionUuid::class)]
#[UsesClass(Sql::class)]
#[UsesClass(SystemWallClock::class)]
#[UsesClass(Transaction::class)]
#[UsesClass(User::class)]
#[UsesClass(UserApproval::class)]
#[UsesClass(UserRepository::class)]
#[UsesClass(UserStatus::class)]
final class RegistrationTest extends SessionEndpointTestCase
{
    public function testRegisterCreatesTheOrganizationAndTheUser(): void
    {
        $tokens = $this->register();

        $organization = $this->row('organizations', 'name', "Bob's Shop");
        $user = $this->row('users', 'username', 'bob');
        $this->assertSame(1, $this->rowCount('organizations'));
        $this->assertSame($organization['id'], $user['organization_id']);
        $this->assertSame('bob@example.com', $user['email']);
        $this->assertSame('0', (string) $user['is_admin']);
        $this->assertSame((int) $user['id'], $tokens->user->id);
        $this->assertSame((int) $organization['id'], $tokens->user->organizationId);
        $this->assertFalse($tokens->user->isAdmin);
    }

    public function testRegisterStoresAVerifiableDigestNeverThePlainPassword(): void
    {
        $this->register();

        $digest = (string) $this->row('users', 'username', 'bob')['password_digest'];
        $this->assertNotSame('s3cretpass', $digest);
        $this->assertStringNotContainsString('s3cretpass', $digest);
        $this->assertTrue(password_verify('s3cretpass', $digest));
        $this->assertFalse(password_needs_rehash($digest, PASSWORD_DEFAULT));
    }

    public function testRegisterStartsOneActiveSession(): void
    {
        $tokens = $this->register();

        $token = $this->row('refresh_tokens', 'user_id', $tokens->user->id);
        $this->assertSame(1, $this->rowCount('refresh_tokens'));
        $this->assertSame(RefreshTokenValue::hash($tokens->refreshToken()), $token['token_hash']);
        $this->assertSame($tokens->sessionUuid, $token['session_uuid']);
        $this->assertNull($token['revoked_at']);
        $this->assertNotNull((new AccessToken(new SecretKeys(self::SECRET_KEY), 600, new SystemWallClock()))
            ->verify($tokens->accessToken()));
    }

    public function testUserIsPendingWhenApprovalIsUnset(): void
    {
        $this->assertSame(UserStatus::Pending, $this->register()->user->status);
        $this->assertSame('pending', $this->row('users', 'username', 'bob')['status']);
    }

    public function testUserIsApprovedWhenApprovalIsExactlyFalse(): void
    {
        putenv('USER_APPROVAL_REQUIRED=false');

        $this->assertSame(UserStatus::Approved, $this->register()->user->status);
        $this->assertSame('approved', $this->row('users', 'username', 'bob')['status']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function approvalValues(): array
    {
        return [
            'true' => ['true'],
            'upper-case FALSE' => ['FALSE'],
            'zero' => ['0'],
            'no' => ['no'],
        ];
    }

    #[DataProvider('approvalValues')]
    public function testUserIsPendingForAnyOtherApprovalValue(string $value): void
    {
        putenv("USER_APPROVAL_REQUIRED={$value}");

        $this->assertSame(UserStatus::Pending, $this->register()->user->status);
    }

    public function testPrecheckReportsATakenUsername(): void
    {
        $this->fixtures->user(username: 'bob', email: 'someone@example.com');

        $this->assertConflict('USERNAME_TAKEN', 'username is not available', fn () => $this->register());
    }

    public function testPrecheckReportsATakenEmail(): void
    {
        $this->fixtures->user(username: 'someone', email: 'bob@example.com');

        $this->assertConflict('EMAIL_TAKEN', 'email is not available', fn () => $this->register());
    }

    public function testPrecheckReportsTheUsernameFirstWhenBothAreTakenByDifferentUsers(): void
    {
        $this->fixtures->user(username: 'someone', email: 'bob@example.com');
        $this->fixtures->user(username: 'bob', email: 'someone-else@example.com');

        $this->assertConflict('USERNAME_TAKEN', 'username is not available', fn () => $this->register());
    }

    public function testPrecheckCountsADeniedUser(): void
    {
        $this->fixtures->user(username: 'bob', status: 'denied');

        $this->assertConflict('USERNAME_TAKEN', 'username is not available', fn () => $this->register());
    }

    public function testPrecheckIgnoresCaseAndAccents(): void
    {
        $this->fixtures->user(username: 'bob', email: 'someone@example.com');

        $this->assertConflict(
            'USERNAME_TAKEN',
            'username is not available',
            fn () => $this->register(username: 'BÓB'),
        );
    }

    public function testRaceOnTheUsernameRollsEverythingBack(): void
    {
        $this->fixtures->user(username: 'bob', email: 'someone@example.com');

        $this->assertConflict('USERNAME_TAKEN', 'username is not available', fn () => $this->create());
        $this->assertNothingWasCreated();
    }

    public function testRaceOnTheEmailRollsEverythingBack(): void
    {
        $this->fixtures->user(username: 'someone', email: 'BOB@example.com');

        $this->assertConflict('EMAIL_TAKEN', 'email is not available', fn () => $this->create());
        $this->assertNothingWasCreated();
    }

    public function testAnotherDatabaseErrorIsRethrownAndRolledBack(): void
    {
        $this->expectException(PDOException::class);

        try {
            $this->create(organizationName: str_repeat('x', 256));
        } finally {
            $this->assertSame(0, $this->rowCount('organizations'));
            $this->assertSame(0, $this->rowCount('users'));
        }
    }

    private function register(string $username = 'bob'): SessionTokens
    {
        return $this->container()->registration()->register(
            $username,
            'bob@example.com',
            's3cretpass',
            "Bob's Shop",
        );
    }

    private function create(string $organizationName = "Bob's Shop"): SessionTokens
    {
        return $this->container()->registration()->create(
            'bob',
            'bob@example.com',
            Fixtures::PASSWORD_DIGEST,
            $organizationName,
        );
    }

    private function assertConflict(string $code, string $message, callable $action): void
    {
        $organizations = $this->rowCount('organizations');

        try {
            $action();
            $this->fail('Expected a ConflictException.');
        } catch (ConflictException $exception) {
            $this->assertSame(409, $exception->status());
            $this->assertSame($code, $exception->errorCode());
            $this->assertSame($message, $exception->errorMessage());
        }

        $this->assertSame($organizations, $this->rowCount('organizations'));
    }

    private function assertNothingWasCreated(): void
    {
        $this->assertSame(1, $this->rowCount('organizations'));
        $this->assertSame(1, $this->rowCount('users'));
        $this->assertSame(0, $this->rowCount('refresh_tokens'));
    }
}
