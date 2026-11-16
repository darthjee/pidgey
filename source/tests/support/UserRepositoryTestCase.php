<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\Sql;
use Pidgey\Repository\UserRepository;

/**
 * Base class for the user repository tests.
 */
abstract class UserRepositoryTestCase extends RepositoryTestCase
{
    protected const SESSION = '0b8f2c3e-1d4a-4b6c-9e7f-a1b2c3d4e5f6';

    protected const OTHER_SESSION = '9a7e6d5c-4b3a-4f2e-8d1c-0b9a8f7e6d5c';

    protected UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new UserRepository($this->pdo);
    }

    protected function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    protected function at(string $relative): string
    {
        return Sql::dateTime($this->now()->modify($relative));
    }

    protected function createUser(
        string $username,
        string $email,
        UserStatus $status = UserStatus::Approved,
        bool $isAdmin = false,
    ): User {
        return $this->repository->create(
            $this->fixtures->organization(),
            $username,
            $email,
            Fixtures::PASSWORD_DIGEST,
            $status,
            $isAdmin,
        );
    }
}
