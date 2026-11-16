<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Model\Row;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\Sql;
use Pidgey\Repository\UserRepository;
use Pidgey\Tests\Support\UserRepositoryTestCase;

#[CoversClass(UserRepository::class)]
#[UsesClass(User::class)]
#[UsesClass(UserStatus::class)]
#[UsesClass(Row::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(Sql::class)]
final class UserRepositoryTakenTest extends UserRepositoryTestCase
{
    public function testFindTakenFindsAUsernameMatch(): void
    {
        $id = $this->fixtures->user(username: 'frank', email: 'frank@example.com');

        $this->assertSame($id, $this->repository->findTaken('frank', 'other@example.com')?->id);
    }

    public function testFindTakenFindsAnEmailMatch(): void
    {
        $id = $this->fixtures->user(username: 'frank', email: 'frank@example.com');

        $this->assertSame($id, $this->repository->findTaken('other', 'frank@example.com')?->id);
    }

    public function testFindTakenPrefersTheUsernameRowWhenTwoRowsMatch(): void
    {
        $this->fixtures->user(username: 'first', email: 'taken@example.com');
        $usernameId = $this->fixtures->user(username: 'taken', email: 'second@example.com');

        $this->assertSame($usernameId, $this->repository->findTaken('taken', 'taken@example.com')?->id);
    }

    public function testFindTakenPrefersTheUsernameRowWhateverTheInsertOrder(): void
    {
        $usernameId = $this->fixtures->user(username: 'taken', email: 'second@example.com');
        $this->fixtures->user(username: 'first', email: 'taken@example.com');

        $this->assertSame($usernameId, $this->repository->findTaken('taken', 'taken@example.com')?->id);
    }

    public function testFindTakenIgnoresCaseAndAccents(): void
    {
        $bobId = $this->fixtures->user(username: 'bob', email: 'bob@example.com');

        $this->assertSame($bobId, $this->repository->findTaken('BOB', 'new@example.com')?->id);
        $this->assertSame($bobId, $this->repository->findTaken('bób', 'new@example.com')?->id);
        $this->assertSame($bobId, $this->repository->findTaken('new', 'BÓB@example.com')?->id);
    }

    public function testFindTakenCountsADeniedUser(): void
    {
        $id = $this->fixtures->user(username: 'denied', status: 'denied');

        $this->assertSame($id, $this->repository->findTaken('denied', 'new@example.com')?->id);
    }

    public function testFindTakenReturnsNullWhenNothingMatches(): void
    {
        $this->fixtures->user(username: 'frank', email: 'frank@example.com');

        $this->assertNull($this->repository->findTaken('george', 'george@example.com'));
    }

    public function testUsernameTakenByOtherIgnoresTheCallersOwnRow(): void
    {
        $user = $this->createUser('Bob', 'bob@example.com');

        $this->assertFalse($this->repository->usernameTakenByOther('Bob', $user->id));
        $this->assertFalse($this->repository->usernameTakenByOther('bob', $user->id));
    }

    public function testUsernameTakenByOtherMatchesAnotherUserCaseAndAccentInsensitively(): void
    {
        $user = $this->createUser('alice', 'alice@example.com');
        $this->createUser('José', 'jose@example.com');

        $this->assertTrue($this->repository->usernameTakenByOther('José', $user->id));
        $this->assertTrue($this->repository->usernameTakenByOther('JOSE', $user->id));
        $this->assertFalse($this->repository->usernameTakenByOther('josé ', $user->id));
        $this->assertFalse($this->repository->usernameTakenByOther('nobody', $user->id));
    }

    public function testEmailTakenByOtherIgnoresTheCallersOwnRow(): void
    {
        $user = $this->createUser('bob', 'Bob@Example.com');

        $this->assertFalse($this->repository->emailTakenByOther('bob@example.com', $user->id));
    }

    public function testEmailTakenByOtherMatchesAnotherUserCaseInsensitively(): void
    {
        $user = $this->createUser('alice', 'alice@example.com');
        $this->createUser('carol', 'carol@example.com');

        $this->assertTrue($this->repository->emailTakenByOther('CAROL@example.com', $user->id));
        $this->assertFalse($this->repository->emailTakenByOther('dave@example.com', $user->id));
    }
}
