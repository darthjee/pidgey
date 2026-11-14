<?php

declare(strict_types=1);

namespace Pidgey;

use InvalidArgumentException;
use PDO;
use Pidgey\Auth\AccessToken;
use Pidgey\Auth\AccountEdit;
use Pidgey\Auth\Login;
use Pidgey\Auth\Passwords;
use Pidgey\Auth\Registration;
use Pidgey\Auth\SecretKeys;
use Pidgey\Auth\SessionRotation;
use Pidgey\Auth\Sessions;
use Pidgey\Crypto\EncryptionKeys;
use Pidgey\Crypto\PasswordCipher;
use Pidgey\Email\MimeBuilder;
use Pidgey\Email\SendFlow;
use Pidgey\Http\OperatorLog;
use Pidgey\Infra\AppPath;
use Pidgey\Infra\Clock;
use Pidgey\Infra\ConfigValidator;
use Pidgey\Infra\ConnectionFactory;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\LoggerFactory;
use Pidgey\Infra\PdoFactory;
use Pidgey\Infra\SystemClock;
use Pidgey\Infra\SystemWallClock;
use Pidgey\Infra\Transaction;
use Pidgey\Infra\UserApproval;
use Pidgey\Infra\WallClock;
use Pidgey\Repository\AccountEditLockoutRepository;
use Pidgey\Repository\ApiTokenRepository;
use Pidgey\Repository\AuthorizationRequestRepository;
use Pidgey\Repository\OrganizationRepository;
use Pidgey\Repository\PasswordResetTokenRepository;
use Pidgey\Repository\RefreshTokenRepository;
use Pidgey\Repository\SmtpConnectionRepository;
use Pidgey\Repository\UserRepository;
use Pidgey\Smtp\HostPolicy;
use Pidgey\Smtp\SessionSettings;
use Pidgey\Smtp\SmtpTransport;
use Pidgey\Smtp\SystemHostResolver;
use Pidgey\Smtp\Transport;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Composition root: builds the services of one request through typed, lazy, memoized getters.
 *
 * Nothing is opened until a getter is called, so a request that never touches the database
 * never connects (and needs no DB_* variables).
 */
final class Container
{
    private ?PDO $pdo = null;

    private ?Transaction $transaction = null;

    private ?OrganizationRepository $organizationRepository = null;

    private ?UserRepository $userRepository = null;

    private ?RefreshTokenRepository $refreshTokenRepository = null;

    private ?PasswordResetTokenRepository $passwordResetTokenRepository = null;

    private ?AuthorizationRequestRepository $authorizationRequestRepository = null;

    private ?AccountEditLockoutRepository $accountEditLockoutRepository = null;

    private ?ApiTokenRepository $apiTokenRepository = null;

    private ?SmtpConnectionRepository $smtpConnectionRepository = null;

    private ?AccessToken $accessToken = null;

    private ?Sessions $sessions = null;

    private ?SessionRotation $sessionRotation = null;

    private ?Passwords $passwords = null;

    private ?Registration $registration = null;

    private ?Login $login = null;

    private ?AccountEdit $accountEdit = null;

    private ?EncryptionKeys $encryptionKeys = null;

    private ?PasswordCipher $passwordCipher = null;

    private ?SessionSettings $smtpSessionSettings = null;

    private ?Transport $smtpTransport = null;

    private ?OperatorLog $operatorLog = null;

    private ?MimeBuilder $mimeBuilder = null;

    private ?SendFlow $sendFlow = null;

    /**
     * Creates an empty container; nothing is built yet.
     *
     * New callers pass the arguments after `$wallClock` by name; the order never changes.
     *
     * @param ConnectionFactory    $connectionFactory Opens the database connection (swappable in tests).
     * @param WallClock|null       $wallClock         Wall clock (swappable in tests); defaults to the
     *                                                system clock, built on first use.
     * @param Clock|null           $clock             Monotonic clock (swappable in tests); defaults to
     *                                                the system clock, built on first use.
     * @param LoggerInterface|null $logger            Application logger (swappable in tests);
     *                                                defaults to the one built from `LOG_LEVEL`, on
     *                                                first use.
     * @param HostPolicy|null      $hostPolicy        SMTP host policy (swappable in tests);
     *                                                defaults to the system resolver and
     *                                                `SMTP_ALLOW_PRIVATE_HOSTS`, on first use.
     */
    public function __construct(
        private readonly ConnectionFactory $connectionFactory = new PdoFactory(),
        private ?WallClock $wallClock = null,
        private ?Clock $clock = null,
        private ?LoggerInterface $logger = null,
        private ?HostPolicy $hostPolicy = null,
    ) {
    }

    /**
     * Returns the request's wall clock.
     *
     * @return WallClock
     */
    public function wallClock(): WallClock
    {
        return $this->wallClock ??= new SystemWallClock();
    }

    /**
     * Returns the request's monotonic clock, for durations, timeouts and pauses.
     *
     * @return Clock
     */
    public function clock(): Clock
    {
        return $this->clock ??= new SystemClock();
    }

    /**
     * Returns the application logger, built from `LOG_LEVEL` on the first call, so a request that
     * never logs never builds one.
     *
     * @return LoggerInterface
     *
     * @throws InvalidArgumentException When `LOG_LEVEL` is invalid.
     */
    public function logger(): LoggerInterface
    {
        return $this->logger ??= LoggerFactory::fromEnv();
    }

    /**
     * Returns the operator log of the admin endpoints, written through the application logger.
     *
     * @return OperatorLog
     *
     * @throws InvalidArgumentException When `LOG_LEVEL` is invalid.
     */
    public function operatorLog(): OperatorLog
    {
        return $this->operatorLog ??= new OperatorLog($this->logger());
    }

    /**
     * Tells whether new registrations start `pending`, read from `USER_APPROVAL_REQUIRED` on
     * each call (only the exact value `false` disables approval).
     *
     * @return boolean
     */
    public function userApprovalRequired(): bool
    {
        return UserApproval::required();
    }

    /**
     * Returns the access-token service, built from `SECRET_KEY`, `PREVIOUS_SECRET_KEYS` and
     * `ACCESS_TOKEN_TTL` on the first call.
     *
     * @return AccessToken
     *
     * @throws RuntimeException When `SECRET_KEY` is missing or `ACCESS_TOKEN_TTL` is invalid.
     */
    public function accessToken(): AccessToken
    {
        return $this->accessToken ??= new AccessToken(
            SecretKeys::fromEnv(),
            Env::integer('ACCESS_TOKEN_TTL', ConfigValidator::DEFAULT_ACCESS_TOKEN_TTL),
            $this->wallClock(),
        );
    }

    /**
     * Returns the SMTP password encryption keys, read from `SMTP_ENCRYPTION_KEY` and
     * `PREVIOUS_SMTP_ENCRYPTION_KEYS` and derived on the first call.
     *
     * @return EncryptionKeys
     *
     * @throws RuntimeException When `SMTP_ENCRYPTION_KEY` is missing.
     */
    public function encryptionKeys(): EncryptionKeys
    {
        return $this->encryptionKeys ??= EncryptionKeys::fromEnv();
    }

    /**
     * Returns the SMTP password cipher, built over the encryption keys on the first call; opens
     * no connection.
     *
     * @return PasswordCipher
     *
     * @throws RuntimeException When `SMTP_ENCRYPTION_KEY` is missing.
     */
    public function passwordCipher(): PasswordCipher
    {
        return $this->passwordCipher ??= new PasswordCipher($this->encryptionKeys());
    }

    /**
     * Returns the effective `SMTP_TEST_MAX_ATTEMPTS` (default applied).
     *
     * @return integer
     *
     * @throws RuntimeException When the value is not a non-negative integer.
     */
    public function smtpTestMaxAttempts(): int
    {
        return Env::integer('SMTP_TEST_MAX_ATTEMPTS', ConfigValidator::DEFAULT_SMTP_TEST_MAX_ATTEMPTS);
    }

    /**
     * Returns the effective `SMTP_TEST_WINDOW`, in seconds (default applied).
     *
     * @return integer
     *
     * @throws RuntimeException When the value is not a non-negative integer.
     */
    public function smtpTestWindow(): int
    {
        return Env::integer('SMTP_TEST_WINDOW', ConfigValidator::DEFAULT_SMTP_TEST_WINDOW);
    }

    /**
     * Returns the SMTP transport settings, read from `SMTP_CONNECT_TIMEOUT`,
     * `SMTP_COMMAND_TIMEOUT`, `SMTP_TOTAL_TIMEOUT`, `SMTP_CA_FILE` (resolved against the app
     * root) and `SMTP_EHLO_NAME` on the first call.
     *
     * @return SessionSettings
     *
     * @throws RuntimeException When a timeout is not a non-negative integer.
     */
    public function smtpSessionSettings(): SessionSettings
    {
        $caFile = Env::get('SMTP_CA_FILE');

        return $this->smtpSessionSettings ??= new SessionSettings(
            (float) Env::integer('SMTP_CONNECT_TIMEOUT', ConfigValidator::DEFAULT_SMTP_CONNECT_TIMEOUT),
            (float) Env::integer('SMTP_COMMAND_TIMEOUT', ConfigValidator::DEFAULT_SMTP_COMMAND_TIMEOUT),
            (float) Env::integer('SMTP_TOTAL_TIMEOUT', ConfigValidator::DEFAULT_SMTP_TOTAL_TIMEOUT),
            $caFile === null ? null : AppPath::resolve($caFile),
            Env::get('SMTP_EHLO_NAME'),
        );
    }

    /**
     * Returns the SMTP transport, built over the monotonic clock on the first call; opens no
     * connection (each `send()` opens its own).
     *
     * @return Transport
     */
    public function smtpTransport(): Transport
    {
        return $this->smtpTransport ??= new SmtpTransport($this->clock());
    }

    /**
     * Returns the SMTP host policy, built over the system resolver on the first call, with
     * private hosts allowed only when `SMTP_ALLOW_PRIVATE_HOSTS` is exactly `true`.
     *
     * @return HostPolicy
     */
    public function hostPolicy(): HostPolicy
    {
        return $this->hostPolicy ??= new HostPolicy(
            new SystemHostResolver(),
            Env::get('SMTP_ALLOW_PRIVATE_HOSTS') === 'true',
        );
    }

    /**
     * Returns the MIME builder, over the wall clock (opens no connection).
     *
     * @return MimeBuilder
     */
    public function mimeBuilder(): MimeBuilder
    {
        return $this->mimeBuilder ??= new MimeBuilder($this->wallClock());
    }

    /**
     * Returns the send flow, built over the host policy, the password cipher, the MIME
     * builder, the SMTP transport and its settings, and the logger on the first call; opens
     * no connection.
     *
     * @return SendFlow
     *
     * @throws RuntimeException When `SMTP_ENCRYPTION_KEY` is missing or a timeout is invalid.
     */
    public function sendFlow(): SendFlow
    {
        return $this->sendFlow ??= new SendFlow(
            $this->hostPolicy(),
            $this->passwordCipher(),
            $this->mimeBuilder(),
            $this->smtpTransport(),
            $this->smtpSessionSettings(),
            $this->logger(),
        );
    }

    /**
     * Returns the sessions service, built from the request's connection, the access-token service,
     * the wall clock and `REFRESH_TOKEN_TTL` on the first call (which opens the connection).
     *
     * @return Sessions
     *
     * @throws RuntimeException When `SECRET_KEY`, a TTL or a required DB_* variable is missing or
     *                          invalid.
     */
    public function sessions(): Sessions
    {
        return $this->sessions ??= new Sessions(
            $this->refreshTokenRepository(),
            $this->userRepository(),
            $this->sessionRotation(),
            $this->accessToken(),
            $this->wallClock(),
            self::refreshTokenTtl(),
        );
    }

    /**
     * Returns the atomic rotation step of a refresh, built from the request's connection, the
     * access-token service and `REFRESH_TOKEN_TTL` on the first call (which opens the
     * connection).
     *
     * @return SessionRotation
     *
     * @throws RuntimeException When `SECRET_KEY`, a TTL or a required DB_* variable is missing or
     *                          invalid.
     */
    public function sessionRotation(): SessionRotation
    {
        return $this->sessionRotation ??= new SessionRotation(
            $this->refreshTokenRepository(),
            $this->transaction(),
            $this->accessToken(),
            self::refreshTokenTtl(),
        );
    }

    /**
     * Returns the password hashing and verification helper (opens no connection).
     *
     * @return Passwords
     */
    public function passwords(): Passwords
    {
        return $this->passwords ??= new Passwords();
    }

    /**
     * Returns the registration service, built from the request's connection, the sessions
     * service and `USER_APPROVAL_REQUIRED` on the first call (which opens the connection).
     *
     * @return Registration
     *
     * @throws RuntimeException When `SECRET_KEY`, a TTL or a required DB_* variable is missing or
     *                          invalid.
     */
    public function registration(): Registration
    {
        return $this->registration ??= new Registration(
            $this->transaction(),
            $this->organizationRepository(),
            $this->userRepository(),
            $this->sessions(),
            $this->passwords(),
            $this->userApprovalRequired(),
        );
    }

    /**
     * Returns the login service, built from the request's connection and the sessions service on
     * the first call (which opens the connection).
     *
     * @return Login
     *
     * @throws RuntimeException When `SECRET_KEY`, a TTL or a required DB_* variable is missing or
     *                          invalid.
     */
    public function login(): Login
    {
        return $this->login ??= new Login($this->userRepository(), $this->sessions(), $this->passwords());
    }

    /**
     * Returns the account edit service, built from the request's connection, the wall clock,
     * `ACCOUNT_EDIT_MAX_ATTEMPTS` and `ACCOUNT_EDIT_LOCK_TTL` on the first call (which opens the
     * connection).
     *
     * @return AccountEdit
     *
     * @throws RuntimeException When a setting or a required DB_* variable is missing or invalid.
     */
    public function accountEdit(): AccountEdit
    {
        return $this->accountEdit ??= new AccountEdit(
            $this->transaction(),
            $this->accountEditLockoutRepository(),
            $this->userRepository(),
            $this->passwordResetTokenRepository(),
            $this->passwords(),
            $this->passwords(),
            $this->wallClock(),
            Env::integer('ACCOUNT_EDIT_MAX_ATTEMPTS', ConfigValidator::DEFAULT_ACCOUNT_EDIT_MAX_ATTEMPTS),
            Env::integer('ACCOUNT_EDIT_LOCK_TTL', ConfigValidator::DEFAULT_ACCOUNT_EDIT_LOCK_TTL),
        );
    }

    /**
     * Returns the request's database connection, opening it on the first call.
     *
     * @return PDO
     *
     * @throws RuntimeException When a required DB_* variable is missing or invalid.
     */
    public function pdo(): PDO
    {
        return $this->pdo ??= $this->connectionFactory->create(DatabaseConfig::fromEnv());
    }

    /**
     * Returns the transaction helper bound to the request's connection.
     *
     * @return Transaction
     *
     * @throws RuntimeException When a required DB_* variable is missing or invalid.
     */
    public function transaction(): Transaction
    {
        return $this->transaction ??= new Transaction($this->pdo());
    }

    /**
     * Returns the `organizations` repository, bound to the request's connection.
     *
     * @return OrganizationRepository
     *
     * @throws RuntimeException When a required DB_* variable is missing or invalid.
     */
    public function organizationRepository(): OrganizationRepository
    {
        return $this->organizationRepository ??= new OrganizationRepository($this->pdo());
    }

    /**
     * Returns the `users` repository, bound to the request's connection.
     *
     * @return UserRepository
     *
     * @throws RuntimeException When a required DB_* variable is missing or invalid.
     */
    public function userRepository(): UserRepository
    {
        return $this->userRepository ??= new UserRepository($this->pdo());
    }

    /**
     * Returns the `refresh_tokens` repository, bound to the request's connection.
     *
     * @return RefreshTokenRepository
     *
     * @throws RuntimeException When a required DB_* variable is missing or invalid.
     */
    public function refreshTokenRepository(): RefreshTokenRepository
    {
        return $this->refreshTokenRepository ??= new RefreshTokenRepository($this->pdo());
    }

    /**
     * Returns the `password_reset_tokens` repository, bound to the request's connection.
     *
     * @return PasswordResetTokenRepository
     *
     * @throws RuntimeException When a required DB_* variable is missing or invalid.
     */
    public function passwordResetTokenRepository(): PasswordResetTokenRepository
    {
        return $this->passwordResetTokenRepository ??= new PasswordResetTokenRepository($this->pdo());
    }

    /**
     * Returns the `authorization_requests` repository, bound to the request's connection.
     *
     * @return AuthorizationRequestRepository
     *
     * @throws RuntimeException When a required DB_* variable is missing or invalid.
     */
    public function authorizationRequestRepository(): AuthorizationRequestRepository
    {
        return $this->authorizationRequestRepository ??= new AuthorizationRequestRepository($this->pdo());
    }

    /**
     * Returns the `account_edit_lockouts` repository, bound to the request's connection.
     *
     * @return AccountEditLockoutRepository
     *
     * @throws RuntimeException When a required DB_* variable is missing or invalid.
     */
    public function accountEditLockoutRepository(): AccountEditLockoutRepository
    {
        return $this->accountEditLockoutRepository ??= new AccountEditLockoutRepository($this->pdo());
    }

    /**
     * Returns the `api_tokens` repository, bound to the request's connection.
     *
     * @return ApiTokenRepository
     *
     * @throws RuntimeException When a required DB_* variable is missing or invalid.
     */
    public function apiTokenRepository(): ApiTokenRepository
    {
        return $this->apiTokenRepository ??= new ApiTokenRepository($this->pdo());
    }

    /**
     * Returns the `smtp_connections` repository, bound to the request's connection.
     *
     * @return SmtpConnectionRepository
     *
     * @throws RuntimeException When a required DB_* variable is missing or invalid.
     */
    public function smtpConnectionRepository(): SmtpConnectionRepository
    {
        return $this->smtpConnectionRepository ??= new SmtpConnectionRepository($this->pdo());
    }

    /**
     * Reads `REFRESH_TOKEN_TTL`, in seconds.
     *
     * @return integer
     *
     * @throws RuntimeException When the value is not a non-negative integer.
     */
    private static function refreshTokenTtl(): int
    {
        return Env::integer('REFRESH_TOKEN_TTL', ConfigValidator::DEFAULT_REFRESH_TOKEN_TTL);
    }
}
