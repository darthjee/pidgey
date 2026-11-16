<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Monolog\Handler\AbstractHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\ConnectionFactory;

/**
 * Shared environment handling and helpers for the Container tests.
 */
abstract class ContainerTestCase extends TestCase
{
    protected const VARIABLES = [
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
        'SECRET_KEY',
        'PREVIOUS_SECRET_KEYS',
        'SMTP_ENCRYPTION_KEY',
        'PREVIOUS_SMTP_ENCRYPTION_KEYS',
        'ACCESS_TOKEN_TTL',
        'REFRESH_TOKEN_TTL',
        'USER_APPROVAL_REQUIRED',
        'ACCOUNT_EDIT_MAX_ATTEMPTS',
        'ACCOUNT_EDIT_LOCK_TTL',
        'LOG_LEVEL',
        'SMTP_CONNECT_TIMEOUT',
        'SMTP_COMMAND_TIMEOUT',
        'SMTP_TOTAL_TIMEOUT',
        'SMTP_CA_FILE',
        'SMTP_EHLO_NAME',
        'SMTP_ALLOW_PRIVATE_HOSTS',
    ];

    protected const SECRET_KEY = 'container-test-secret-key-0123456789abcdef';
    protected const SMTP_KEY = 'container-test-smtp-encryption-key-0123456789abcdef';
    protected const PREVIOUS_SMTP_KEY = 'container-test-previous-smtp-encryption-key-0123456789';

    /** @var array<string, string|false> */
    private array $saved = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->saved[$name] = getenv($name);
        }

        putenv('DB_HOST=db.example');
        putenv('DB_PORT=3307');
        putenv('DB_NAME=pidgey_example_test');
        putenv('DB_USER=someone');
        putenv('DB_PASSWORD=secret');
        putenv('SECRET_KEY=' . self::SECRET_KEY);
        putenv('PREVIOUS_SECRET_KEYS');
        putenv('SMTP_ENCRYPTION_KEY=' . self::SMTP_KEY);
        putenv('PREVIOUS_SMTP_ENCRYPTION_KEYS');
        putenv('ACCESS_TOKEN_TTL');
        putenv('REFRESH_TOKEN_TTL');
        putenv('USER_APPROVAL_REQUIRED');
        putenv('ACCOUNT_EDIT_MAX_ATTEMPTS');
        putenv('ACCOUNT_EDIT_LOCK_TTL');
        putenv('LOG_LEVEL');
        putenv('SMTP_CONNECT_TIMEOUT');
        putenv('SMTP_COMMAND_TIMEOUT');
        putenv('SMTP_TOTAL_TIMEOUT');
        putenv('SMTP_CA_FILE');
        putenv('SMTP_EHLO_NAME');
        putenv('SMTP_ALLOW_PRIVATE_HOSTS');
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }
    }

    protected function unusedConnections(): ConnectionFactory
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->never())->method('create');
        $factory->expects($this->never())->method('createServer');

        return $factory;
    }

    protected function handlerLevel(Logger $logger): Level
    {
        $handlers = $logger->getHandlers();
        $this->assertCount(1, $handlers);
        $this->assertInstanceOf(AbstractHandler::class, $handlers[0]);

        return $handlers[0]->getLevel();
    }
}
