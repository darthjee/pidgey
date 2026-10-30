<?php

declare(strict_types=1);

namespace Pidgey;

use PDO;
use Pidgey\Infra\ConnectionFactory;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\PdoFactory;
use Pidgey\Infra\Transaction;
use Pidgey\Repository\AccountEditLockoutRepository;
use Pidgey\Repository\ApiTokenRepository;
use Pidgey\Repository\AuthorizationRequestRepository;
use Pidgey\Repository\OrganizationRepository;
use Pidgey\Repository\PasswordResetTokenRepository;
use Pidgey\Repository\RefreshTokenRepository;
use Pidgey\Repository\SmtpConnectionRepository;
use Pidgey\Repository\UserRepository;
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

    /**
     * Creates an empty container; nothing is built yet.
     *
     * @param ConnectionFactory $connectionFactory Opens the database connection (swappable in tests).
     */
    public function __construct(private readonly ConnectionFactory $connectionFactory = new PdoFactory())
    {
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
}
