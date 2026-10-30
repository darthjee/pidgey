<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use PDO;

/**
 * Base class for repository tests: each test runs inside a transaction that is rolled back.
 *
 * Tests must not run DDL (it commits implicitly), must not rely on literal ids
 * (`AUTO_INCREMENT` is not rolled back), and the code under test must not open its own
 * transaction.
 */
abstract class RepositoryTestCase extends IntegrationTestCase
{
    protected PDO $pdo;

    protected Fixtures $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = self::pdo();
        $this->pdo->beginTransaction();
        $this->fixtures = new Fixtures($this->pdo);
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        parent::tearDown();
    }
}
