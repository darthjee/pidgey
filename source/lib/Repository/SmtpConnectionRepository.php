<?php

declare(strict_types=1);

namespace Pidgey\Repository;

use LogicException;
use PDO;
use PDOException;
use Pidgey\Model\SmtpConnection;
use Pidgey\Model\SmtpEncryption;
use SensitiveParameter;

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
     * Lists every connection that stores a password, across all organizations, in ascending id
     * order; used only by the key rotation command (`bin/reencrypt-smtp-passwords`).
     *
     * @return list<array{id: integer, organizationId: integer}> Ids only, never the stored bytes.
     *
     * @throws PDOException When the statement fails.
     */
    public function listIdsWithPassword(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, organization_id FROM smtp_connections WHERE password IS NOT NULL ORDER BY id',
        );

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'organizationId' => (int) $row['organization_id'],
            ],
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /**
     * Reads the stored password bytes of one connection, without locking.
     *
     * @param integer $organizationId Organization the connection must belong to.
     * @param integer $id             Primary key.
     *
     * @return string|null The stored bytes; null when the row is gone or stores no password.
     *
     * @throws PDOException When the statement fails.
     */
    public function findEncryptedPassword(int $organizationId, int $id): ?string
    {
        return $this->fetchPassword(
            'SELECT password FROM smtp_connections WHERE organization_id = :organization_id AND id = :id',
            $organizationId,
            $id,
        );
    }

    /**
     * Locks one connection (`SELECT … FOR UPDATE`) and reads its stored password bytes; must run
     * inside a transaction, which holds the lock until it ends.
     *
     * @param integer $organizationId Organization the connection must belong to.
     * @param integer $id             Primary key.
     *
     * @return string|null The stored bytes; null when the row is gone or stores no password.
     *
     * @throws PDOException When the statement fails.
     */
    public function lockEncryptedPassword(int $organizationId, int $id): ?string
    {
        return $this->fetchPassword(
            'SELECT password FROM smtp_connections '
            . 'WHERE organization_id = :organization_id AND id = :id FOR UPDATE',
            $organizationId,
            $id,
        );
    }

    /**
     * Replaces the stored password bytes of one connection, keeping `updated_at` unchanged (a
     * re-encryption is not an edit).
     *
     * @param integer $organizationId    Organization the connection must belong to.
     * @param integer $id                Primary key.
     * @param string  $encryptedPassword Ciphertext bytes of the SMTP auth password.
     *
     * @return void
     *
     * @throws PDOException When the statement fails.
     */
    public function replaceEncryptedPassword(
        int $organizationId,
        int $id,
        #[SensitiveParameter] string $encryptedPassword,
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE smtp_connections SET password = :password, updated_at = updated_at '
            . 'WHERE organization_id = :organization_id AND id = :id',
        );
        $statement->bindValue('password', $encryptedPassword, PDO::PARAM_LOB);
        $statement->bindValue('organization_id', $organizationId, PDO::PARAM_INT);
        $statement->bindValue('id', $id, PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Locks one connection of an organization (`SELECT … FOR UPDATE`) and reads it; must run
     * inside a transaction, which holds the row lock until it ends.
     *
     * @param integer $organizationId Organization the connection must belong to.
     * @param integer $id             Primary key.
     *
     * @return SmtpConnection|null Null when no connection of this organization has this id.
     *
     * @throws PDOException When the statement fails.
     */
    public function lockInOrganization(int $organizationId, int $id): ?SmtpConnection
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM smtp_connections '
            . 'WHERE organization_id = :organization_id AND id = :id FOR UPDATE',
            ['organization_id' => $organizationId, 'id' => $id],
        );
    }

    /**
     * Writes the editable settings of one connection: name, host, port, encryption, username,
     * from address and, only when `$passwordChanged`, the password. Never writes `active`, so an
     * update cannot overwrite a concurrent activation or deactivation.
     *
     * Validation (credential pairing, encryption) is the caller's job; the caller should hold
     * the row lock of lockInOrganization() in the same transaction.
     *
     * @param SmtpConnection $merged            Stored row merged with the request; its id and
     *                                          organization id select the row. Its
     *                                          `encryptedPassword` is ignored.
     * @param boolean        $passwordChanged   Whether the password column is written.
     * @param string|null    $encryptedPassword New ciphertext bytes, or null to clear the
     *                                          password; ignored when `$passwordChanged` is
     *                                          false.
     *
     * @return void
     *
     * @throws PDOException When the statement fails.
     */
    public function update(
        SmtpConnection $merged,
        bool $passwordChanged,
        #[SensitiveParameter] ?string $encryptedPassword,
    ): void {
        $password = $passwordChanged ? ', password = :password' : '';
        $statement = $this->pdo->prepare(
            'UPDATE smtp_connections SET name = :name, host = :host, port = :port, '
            . 'encryption = :encryption, username = :username, from_address = :from_address' . $password
            . ' WHERE organization_id = :organization_id AND id = :id',
        );
        $statement->bindValue('name', $merged->name, PDO::PARAM_STR);
        $statement->bindValue('host', $merged->host, PDO::PARAM_STR);
        $statement->bindValue('port', $merged->port, PDO::PARAM_INT);
        $statement->bindValue('encryption', $merged->encryption->value, PDO::PARAM_STR);
        $statement->bindValue(
            'username',
            $merged->username,
            $merged->username === null ? PDO::PARAM_NULL : PDO::PARAM_STR,
        );
        $statement->bindValue('from_address', $merged->fromAddress, PDO::PARAM_STR);

        if ($passwordChanged) {
            $statement->bindValue(
                'password',
                $encryptedPassword,
                $encryptedPassword === null ? PDO::PARAM_NULL : PDO::PARAM_LOB,
            );
        }

        $statement->bindValue('organization_id', $merged->organizationId, PDO::PARAM_INT);
        $statement->bindValue('id', $merged->id, PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Deletes one connection of an organization (hard delete), active or not.
     *
     * @param integer $organizationId Organization the connection must belong to.
     * @param integer $id             Primary key.
     *
     * @return boolean False when no connection of this organization has this id.
     *
     * @throws PDOException When the statement fails.
     */
    public function delete(int $organizationId, int $id): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM smtp_connections WHERE organization_id = :organization_id AND id = :id',
        );
        $statement->execute(['organization_id' => $organizationId, 'id' => $id]);

        return $statement->rowCount() > 0;
    }

    /**
     * Makes one connection the organization's only active connection: first deactivates every
     * other connection of the organization, then activates the target, so the unique index on
     * `active_organization_id` is never hit.
     *
     * Must run inside a transaction that already holds the organization row lock
     * (OrganizationRepository::lockForUpdate()); this is documented, not checked.
     *
     * @param integer $organizationId Organization the connection belongs to.
     * @param integer $id             Primary key of the connection to activate.
     *
     * @return void
     *
     * @throws PDOException When a statement fails.
     */
    public function activate(int $organizationId, int $id): void
    {
        $params = ['organization_id' => $organizationId, 'id' => $id];
        $this->pdo->prepare(
            'UPDATE smtp_connections SET active = FALSE '
            . 'WHERE organization_id = :organization_id AND id <> :id AND active = TRUE',
        )->execute($params);
        $this->pdo->prepare(
            'UPDATE smtp_connections SET active = TRUE WHERE organization_id = :organization_id AND id = :id',
        )->execute($params);
    }

    /**
     * Makes one connection inactive; other connections are never changed.
     *
     * Must run inside a transaction that already holds the organization row lock
     * (OrganizationRepository::lockForUpdate()); this is documented, not checked.
     *
     * @param integer $organizationId Organization the connection belongs to.
     * @param integer $id             Primary key of the connection to deactivate.
     *
     * @return void
     *
     * @throws PDOException When the statement fails.
     */
    public function deactivate(int $organizationId, int $id): void
    {
        $this->pdo->prepare(
            'UPDATE smtp_connections SET active = FALSE WHERE organization_id = :organization_id AND id = :id',
        )->execute(['organization_id' => $organizationId, 'id' => $id]);
    }

    /**
     * Runs a single-row `password` query.
     *
     * @param string  $sql            Statement with `:organization_id` and `:id` placeholders.
     * @param integer $organizationId Organization the connection must belong to.
     * @param integer $id             Primary key.
     *
     * @return string|null
     *
     * @throws PDOException When the statement fails.
     */
    private function fetchPassword(string $sql, int $organizationId, int $id): ?string
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['organization_id' => $organizationId, 'id' => $id]);
        $password = $statement->fetchColumn();

        return is_string($password) ? $password : null;
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
