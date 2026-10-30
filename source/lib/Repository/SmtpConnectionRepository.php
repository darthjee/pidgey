<?php

declare(strict_types=1);

namespace Pidgey\Repository;

use LogicException;
use PDO;
use PDOException;
use Pidgey\Model\SmtpConnection;
use Pidgey\Model\SmtpEncryption;

/**
 * Reads and writes the `smtp_connections` table.
 *
 * Stores the SMTP password as ciphertext only, as opaque bytes: encrypting it happens in the
 * service, before the call. Bound values are never put in an exception message.
 */
final class SmtpConnectionRepository
{
    private const COLUMNS = 'id, organization_id, name, host, port, encryption, username, '
        . 'password, from_address, active, created_at, updated_at';

    /**
     * Creates the repository on the request's connection.
     *
     * @param PDO $pdo Connection to the application database.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Inserts an SMTP connection and returns it, re-read so column defaults (`active = false`,
     * `created_at`, `updated_at`) are reflected: a new connection always starts inactive.
     *
     * `$encryptedPassword` is the ciphertext produced by the service, stored as opaque bytes.
     * The rule that `username` and password are both set or both empty, and deciding which
     * connection is active, are the callers' jobs.
     *
     * @param integer        $organizationId    Organization the connection belongs to.
     * @param string         $name              Label shown in the UI.
     * @param string         $host              SMTP server hostname.
     * @param integer        $port              SMTP port.
     * @param SmtpEncryption $encryption        Transport encryption.
     * @param string|null    $username          SMTP auth username.
     * @param string|null    $encryptedPassword Ciphertext bytes of the SMTP auth password.
     * @param string         $fromAddress       Envelope sender and `From:` header.
     *
     * @return SmtpConnection
     *
     * @throws PDOException   When the statement fails (e.g. an unknown organization).
     * @throws LogicException When the inserted row cannot be read back.
     */
    public function create(
        int $organizationId,
        string $name,
        string $host,
        int $port,
        SmtpEncryption $encryption,
        ?string $username,
        ?string $encryptedPassword,
        string $fromAddress,
    ): SmtpConnection {
        $statement = $this->pdo->prepare(
            'INSERT INTO smtp_connections '
            . '(organization_id, name, host, port, encryption, username, password, from_address) '
            . 'VALUES (:organization_id, :name, :host, :port, :encryption, :username, :password, :from_address)',
        );
        $statement->bindValue('organization_id', $organizationId, PDO::PARAM_INT);
        $statement->bindValue('name', $name, PDO::PARAM_STR);
        $statement->bindValue('host', $host, PDO::PARAM_STR);
        $statement->bindValue('port', $port, PDO::PARAM_INT);
        $statement->bindValue('encryption', $encryption->value, PDO::PARAM_STR);
        $statement->bindValue('username', $username, $username === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(
            'password',
            $encryptedPassword,
            $encryptedPassword === null ? PDO::PARAM_NULL : PDO::PARAM_LOB,
        );
        $statement->bindValue('from_address', $fromAddress, PDO::PARAM_STR);
        $statement->execute();
        $id = (int) $this->pdo->lastInsertId();

        return $this->findInOrganization($organizationId, $id)
            ?? throw new LogicException("SMTP connection {$id} was inserted but cannot be read back.");
    }

    /**
     * Finds an SMTP connection by id within one organization.
     *
     * @param integer $organizationId Organization the connection must belong to.
     * @param integer $id             Primary key.
     *
     * @return SmtpConnection|null Null when no connection of this organization has this id.
     *
     * @throws PDOException When the statement fails.
     */
    public function findInOrganization(int $organizationId, int $id): ?SmtpConnection
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM smtp_connections '
            . 'WHERE organization_id = :organization_id AND id = :id',
            ['organization_id' => $organizationId, 'id' => $id],
        );
    }

    /**
     * Lists the SMTP connections of one organization, in ascending id order.
     *
     * @param integer $organizationId Organization the connections belong to.
     *
     * @return list<SmtpConnection> Empty when the organization has none.
     *
     * @throws PDOException When the statement fails.
     */
    public function listByOrganization(int $organizationId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM smtp_connections '
            . 'WHERE organization_id = :organization_id ORDER BY id',
        );
        $statement->execute(['organization_id' => $organizationId]);

        return array_map(
            static fn (array $row): SmtpConnection => SmtpConnection::fromRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /**
     * Runs a single-row query and builds the SMTP connection, if any.
     *
     * @param string               $sql    Statement with named placeholders.
     * @param array<string, mixed> $params Placeholder name => value.
     *
     * @return SmtpConnection|null
     *
     * @throws PDOException When the statement fails.
     */
    private function fetchOne(string $sql, array $params): ?SmtpConnection
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : SmtpConnection::fromRow($row);
    }
}
