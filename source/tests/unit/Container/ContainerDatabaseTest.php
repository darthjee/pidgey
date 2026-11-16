<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Container;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Auth\AccessToken;
use Pidgey\Auth\AccessTokenClaims;
use Pidgey\Auth\AccountEdit;
use Pidgey\Auth\Base64Url;
use Pidgey\Auth\Login;
use Pidgey\Auth\Passwords;
use Pidgey\Auth\Registration;
use Pidgey\Auth\SecretKeys;
use Pidgey\Auth\SessionRotation;
use Pidgey\Auth\Sessions;
use Pidgey\Container;
use Pidgey\Crypto\EncryptionKeys;
use Pidgey\Crypto\PasswordCipher;
use Pidgey\Email\MimeBuilder;
use Pidgey\Email\SendFlow;
use Pidgey\Http\OperatorLog;
use Pidgey\Infra\AppPath;
use Pidgey\Infra\ConnectionFactory;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\LoggerFactory;
use Pidgey\Infra\SystemClock;
use Pidgey\Infra\SystemWallClock;
use Pidgey\Infra\Transaction;
use Pidgey\Infra\UserApproval;
use Pidgey\Repository\AccountEditLockoutRepository;
use Pidgey\Repository\ApiTokenRepository;
use Pidgey\Repository\AuthorizationRequestRepository;
use Pidgey\Repository\OrganizationRepository;
use Pidgey\Repository\PasswordResetTokenRepository;
use Pidgey\Repository\RefreshTokenRepository;
use Pidgey\Repository\SmtpConnectionRepository;
use Pidgey\Repository\UserRepository;
use Pidgey\Smtp\HostCheck;
use Pidgey\Smtp\HostPolicy;
use Pidgey\Smtp\ReplyParser;
use Pidgey\Smtp\SessionSettings;
use Pidgey\Smtp\SmtpTransport;
use Pidgey\Smtp\SystemHostResolver;
use Pidgey\Tests\Support\ContainerTestCase;
use RuntimeException;

#[CoversClass(Container::class)]
#[UsesClass(AccessToken::class)]
#[UsesClass(AccessTokenClaims::class)]
#[UsesClass(AccountEdit::class)]
#[UsesClass(Base64Url::class)]
#[UsesClass(Login::class)]
#[UsesClass(Passwords::class)]
#[UsesClass(Registration::class)]
#[UsesClass(SecretKeys::class)]
#[UsesClass(SessionRotation::class)]
#[UsesClass(Sessions::class)]
#[UsesClass(SystemClock::class)]
#[UsesClass(SystemWallClock::class)]
#[UsesClass(LoggerFactory::class)]
#[UsesClass(OperatorLog::class)]
#[UsesClass(EncryptionKeys::class)]
#[UsesClass(PasswordCipher::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(Transaction::class)]
#[UsesClass(UserApproval::class)]
#[UsesClass(OrganizationRepository::class)]
#[UsesClass(UserRepository::class)]
#[UsesClass(RefreshTokenRepository::class)]
#[UsesClass(PasswordResetTokenRepository::class)]
#[UsesClass(AuthorizationRequestRepository::class)]
#[UsesClass(AccountEditLockoutRepository::class)]
#[UsesClass(ApiTokenRepository::class)]
#[UsesClass(SmtpConnectionRepository::class)]
#[UsesClass(AppPath::class)]
#[UsesClass(SessionSettings::class)]
#[UsesClass(SmtpTransport::class)]
#[UsesClass(HostPolicy::class)]
#[UsesClass(MimeBuilder::class)]
#[UsesClass(SendFlow::class)]
#[UsesClass(HostCheck::class)]
#[UsesClass(SystemHostResolver::class)]
#[UsesClass(ReplyParser::class)]
final class ContainerDatabaseTest extends ContainerTestCase
{
    public function testConstructorConnectsNothing(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->never())->method('create');
        $factory->expects($this->never())->method('createServer');

        new Container($factory);
    }

    public function testConstructorNeedsNoDatabaseVariables(): void
    {
        putenv('DB_HOST');

        $this->assertInstanceOf(Container::class, new Container());
    }

    public function testPdoConnectsOnceWithTheEnvironmentConfig(): void
    {
        $pdo = $this->createStub(PDO::class);
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with(new DatabaseConfig('db.example', 3307, 'pidgey_example_test', 'someone', 'secret'))
            ->willReturn($pdo);
        $container = new Container($factory);

        $this->assertSame($pdo, $container->pdo());
        $this->assertSame($pdo, $container->pdo());
    }

    public function testPdoReportsAMissingVariableByName(): void
    {
        putenv('DB_HOST');
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->never())->method('create');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB_HOST');

        (new Container($factory))->pdo();
    }

    public function testTransactionIsMemoizedAndWrapsTheSamePdo(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->once())->method('commit')->willReturn(true);
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($pdo);
        $container = new Container($factory);

        $transaction = $container->transaction();

        $this->assertSame($transaction, $container->transaction());
        $this->assertSame($pdo, $container->pdo());
        $this->assertSame('ok', $transaction->run(static fn (): string => 'ok'));
    }

    /**
     * @param list<string> $siblings
     */
    #[DataProvider('repositories')]
    public function testRepositoryIsMemoizedOnASinglePdo(string $accessor, string $class, array $siblings): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($this->createStub(PDO::class));
        $container = new Container($factory);

        $repository = $container->{$accessor}();

        $this->assertInstanceOf($class, $repository);
        $this->assertSame($repository, $container->{$accessor}());
        foreach ($siblings as $sibling) {
            $container->{$sibling}();
        }
    }

    /**
     * @return array<string, array{string, class-string, list<string>}>
     */
    public static function repositories(): array
    {
        return [
            'organization' => [
                'organizationRepository',
                OrganizationRepository::class,
                ['userRepository', 'transaction'],
            ],
            'user' => ['userRepository', UserRepository::class, ['organizationRepository', 'pdo']],
            'refreshToken' => ['refreshTokenRepository', RefreshTokenRepository::class, ['userRepository', 'pdo']],
            'passwordResetToken' => [
                'passwordResetTokenRepository',
                PasswordResetTokenRepository::class,
                ['userRepository', 'pdo'],
            ],
            'authorizationRequest' => [
                'authorizationRequestRepository',
                AuthorizationRequestRepository::class,
                ['userRepository', 'pdo'],
            ],
            'accountEditLockout' => [
                'accountEditLockoutRepository',
                AccountEditLockoutRepository::class,
                ['userRepository', 'pdo'],
            ],
            'apiToken' => ['apiTokenRepository', ApiTokenRepository::class, ['organizationRepository', 'pdo']],
            'smtpConnection' => [
                'smtpConnectionRepository',
                SmtpConnectionRepository::class,
                ['organizationRepository', 'pdo'],
            ],
        ];
    }
}
