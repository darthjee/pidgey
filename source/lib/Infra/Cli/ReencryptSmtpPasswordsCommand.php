<?php

declare(strict_types=1);

namespace Pidgey\Infra\Cli;

use Closure;
use Pidgey\Container;
use Pidgey\Crypto\DecryptionFailed;
use Pidgey\Crypto\PasswordCipher;
use Pidgey\Repository\SmtpConnectionRepository;
use Throwable;

/**
 * The `bin/reencrypt-smtp-passwords` command: re-encrypts with the current
 * `SMTP_ENCRYPTION_KEY` every stored SMTP password whose key id is not the current key's (see
 * `email/password-encryption.md#rotation`).
 *
 * Each row runs in its own transaction: the row is locked (`SELECT … FOR UPDATE`) and its key id
 * re-checked, so a row already written with the current key is skipped and never overwritten.
 * An unreadable row is skipped and reported; processing continues. With `--check` every row is
 * decrypted and nothing is written.
 *
 * Prints the counts and the unreadable ids on stdout, never any password data. Exits 0 when no
 * row is unreadable, 1 otherwise or on any error (message on stderr), 2 on invalid usage.
 */
final class ReencryptSmtpPasswordsCommand
{
    public const EXIT_SUCCESS = 0;
    public const EXIT_FAILURE = 1;
    public const EXIT_USAGE = 2;

    private const REENCRYPTED = 'reencrypted';
    private const CURRENT = 'current';
    private const UNREADABLE = 'unreadable';
    private const GONE = 'gone';

    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    /**
     * Builds the command.
     *
     * @param Closure  $containerFactory Closure(): Container, called once per run.
     * @param resource $stdout           Stream for normal output.
     * @param resource $stderr           Stream for error output.
     */
    public function __construct(private readonly Closure $containerFactory, $stdout, $stderr)
    {
        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }

    /**
     * Builds the command over a container built from the environment (runtime database user,
     * `SMTP_ENCRYPTION_KEY`, `PREVIOUS_SMTP_ENCRYPTION_KEYS`).
     *
     * @param resource $stdout Stream for normal output.
     * @param resource $stderr Stream for error output.
     *
     * @return self
     */
    public static function fromEnvironment($stdout, $stderr): self
    {
        return new self(static fn (): Container => new Container(), $stdout, $stderr);
    }

    /**
     * Runs the re-encryption, or the dry run with `--check`.
     *
     * @param string[] $arguments Command-line arguments, without the script name.
     *
     * @return integer Exit code.
     */
    public function run(array $arguments): int
    {
        if ($arguments !== [] && $arguments !== ['--check']) {
            fwrite($this->stderr, "Usage: reencrypt-smtp-passwords [--check]\n");

            return self::EXIT_USAGE;
        }

        $check = $arguments === ['--check'];

        try {
            $outcomes = $this->processAll(($this->containerFactory)(), $check);
        } catch (Throwable $exception) {
            fwrite($this->stderr, $exception->getMessage() . "\n");

            return self::EXIT_FAILURE;
        }

        $this->report($outcomes, $check);

        return in_array(self::UNREADABLE, $outcomes, true) ? self::EXIT_FAILURE : self::EXIT_SUCCESS;
    }

    /**
     * Processes every row that stores a password.
     *
     * @param Container $container Services of the run.
     * @param boolean   $check     Whether this is the `--check` dry run.
     *
     * @return array<integer, string> Outcome by connection id.
     */
    private function processAll(Container $container, bool $check): array
    {
        $repository = $container->smtpConnectionRepository();
        $cipher = $container->passwordCipher();
        $outcomes = [];

        foreach ($repository->listIdsWithPassword() as $row) {
            $outcomes[$row['id']] = $check
                ? $this->checkRow($repository, $cipher, $row['organizationId'], $row['id'])
                : $container->transaction()->run(
                    fn (): string => $this->reencryptRow($repository, $cipher, $row['organizationId'], $row['id']),
                );
        }

        return $outcomes;
    }

    /**
     * Re-encrypts one locked row with the current key, unless it is already current.
     *
     * @param SmtpConnectionRepository $repository     Stores the bytes.
     * @param PasswordCipher           $cipher         Decrypts and re-encrypts.
     * @param integer                  $organizationId Organization of the connection.
     * @param integer                  $id             Connection id.
     *
     * @return string The row's outcome.
     */
    private function reencryptRow(
        SmtpConnectionRepository $repository,
        PasswordCipher $cipher,
        int $organizationId,
        int $id,
    ): string {
        $stored = $repository->lockEncryptedPassword($organizationId, $id);

        if ($stored === null) {
            return self::GONE;
        }

        // Re-checked after the lock: a newer write with the current key is never overwritten.
        if ($cipher->isCurrent($stored)) {
            return self::CURRENT;
        }

        try {
            $password = $cipher->decrypt($stored, $organizationId, $id);
        } catch (DecryptionFailed) {
            return self::UNREADABLE;
        }

        $repository->replaceEncryptedPassword($organizationId, $id, $cipher->encrypt($password, $organizationId, $id));
        sodium_memzero($password);

        return self::REENCRYPTED;
    }

    /**
     * Decrypts one row without writing, telling whether it is due for re-encryption.
     *
     * @param SmtpConnectionRepository $repository     Reads the bytes.
     * @param PasswordCipher           $cipher         Decrypts.
     * @param integer                  $organizationId Organization of the connection.
     * @param integer                  $id             Connection id.
     *
     * @return string The row's outcome.
     */
    private function checkRow(
        SmtpConnectionRepository $repository,
        PasswordCipher $cipher,
        int $organizationId,
        int $id,
    ): string {
        $stored = $repository->findEncryptedPassword($organizationId, $id);

        if ($stored === null) {
            return self::GONE;
        }

        try {
            $password = $cipher->decrypt($stored, $organizationId, $id);
        } catch (DecryptionFailed) {
            return self::UNREADABLE;
        }

        sodium_memzero($password);

        return $cipher->isCurrent($stored) ? self::CURRENT : self::REENCRYPTED;
    }

    /**
     * Prints the counts and the unreadable ids.
     *
     * @param array<integer, string> $outcomes Outcome by connection id.
     * @param boolean                $check    Whether this was the `--check` dry run.
     *
     * @return void
     */
    private function report(array $outcomes, bool $check): void
    {
        $counts = array_count_values($outcomes);
        $unreadable = array_keys($outcomes, self::UNREADABLE, true);

        $this->write(($check ? 'due: ' : 're-encrypted: ') . ($counts[self::REENCRYPTED] ?? 0));
        $this->write('already current: ' . ($counts[self::CURRENT] ?? 0));
        $this->write('unreadable: ' . count($unreadable));

        if ($unreadable !== []) {
            $this->write('unreadable ids: ' . implode(', ', $unreadable));
        }
    }

    /**
     * Writes a line to stdout.
     *
     * @param string $line Line to write.
     *
     * @return void
     */
    private function write(string $line): void
    {
        fwrite($this->stdout, $line . "\n");
    }
}
