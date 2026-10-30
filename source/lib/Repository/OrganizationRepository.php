<?php

declare(strict_types=1);

namespace Pidgey\Repository;

use LogicException;
use PDO;
use PDOException;
use Pidgey\Model\Organization;

/**
 * Reads and writes the `organizations` table.
 */
final class OrganizationRepository
{
    private const COLUMNS = 'id, name, created_at, updated_at';

    /**
     * Creates the repository on the request's connection.
     *
     * @param PDO $pdo Connection to the application database.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Inserts an organization and returns it, re-read so column defaults are reflected.
     *
     * @param string $name Organization name, stored as given.
     *
     * @return Organization
     *
     * @throws PDOException   When the statement fails.
     * @throws LogicException When the inserted row cannot be read back.
     */
    public function create(string $name): Organization
    {
        $this->pdo->prepare('INSERT INTO organizations (name) VALUES (:name)')
            ->execute(['name' => $name]);
        $id = (int) $this->pdo->lastInsertId();

        return $this->findById($id)
            ?? throw new LogicException("Organization {$id} was inserted but cannot be read back.");
    }

    /**
     * Finds an organization by id.
     *
     * @param integer $id Primary key.
     *
     * @return Organization|null Null when no organization has this id.
     *
     * @throws PDOException When the statement fails.
     */
    public function findById(int $id): ?Organization
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM organizations WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : Organization::fromRow($row);
    }
}
