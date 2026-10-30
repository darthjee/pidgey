<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Model;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Model\Row;
use Pidgey\Model\SmtpConnection;
use Pidgey\Model\SmtpEncryption;
use UnexpectedValueException;

#[CoversClass(SmtpConnection::class)]
#[UsesClass(Row::class)]
final class SmtpConnectionTest extends TestCase
{
    private const PASSWORD_BYTES = "\x00\xff\xfe cipher \x80\x00";

    public function testFromRowMapsEveryColumn(): void
    {
        $connection = SmtpConnection::fromRow($this->row());

        $this->assertSame(12, $connection->id);
        $this->assertSame(7, $connection->organizationId);
        $this->assertSame('Primary relay', $connection->name);
        $this->assertSame('smtp.example.com', $connection->host);
        $this->assertSame(587, $connection->port);
        $this->assertSame(SmtpEncryption::Tls, $connection->encryption);
        $this->assertSame('mailer', $connection->username);
        $this->assertSame(self::PASSWORD_BYTES, $connection->encryptedPassword);
        $this->assertSame('noreply@example.com', $connection->fromAddress);
        $this->assertTrue($connection->active);
        $this->assertSame('2026-01-02 03:04:05', $connection->createdAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $connection->createdAt->getTimezone()->getName());
        $this->assertSame('2026-01-03 04:05:06', $connection->updatedAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $connection->updatedAt->getTimezone()->getName());
    }

    public function testFromRowAcceptsIntegerPortAndActive(): void
    {
        $connection = SmtpConnection::fromRow(['port' => 465, 'active' => 0] + $this->row());

        $this->assertSame(465, $connection->port);
        $this->assertFalse($connection->active);
    }

    public function testFromRowAcceptsStringActiveFalse(): void
    {
        $connection = SmtpConnection::fromRow(['active' => '0'] + $this->row());

        $this->assertFalse($connection->active);
    }

    #[DataProvider('encryptions')]
    public function testFromRowMapsEveryEncryption(string $value, SmtpEncryption $expected): void
    {
        $connection = SmtpConnection::fromRow(['encryption' => $value] + $this->row());

        $this->assertSame($expected, $connection->encryption);
    }

    /**
     * @return array<string, array{string, SmtpEncryption}>
     */
    public static function encryptions(): array
    {
        return [
            'none' => ['none', SmtpEncryption::None],
            'tls' => ['tls', SmtpEncryption::Tls],
            'ssl' => ['ssl', SmtpEncryption::Ssl],
        ];
    }

    public function testFromRowMapsNullCredentials(): void
    {
        $connection = SmtpConnection::fromRow(['username' => null, 'password' => null] + $this->row());

        $this->assertNull($connection->username);
        $this->assertNull($connection->encryptedPassword);
    }

    public function testFromRowRejectsAMissingColumn(): void
    {
        $row = $this->row();
        unset($row['password']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(SmtpConnection::class . ": missing column 'password'");

        SmtpConnection::fromRow($row);
    }

    public function testFromRowRejectsAnUnknownEncryption(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            SmtpConnection::class . ": column 'encryption' must hold a case of " . SmtpEncryption::class,
        );

        SmtpConnection::fromRow(['encryption' => 'starttls'] + $this->row());
    }

    public function testHasPasswordIsTrueWithAPassword(): void
    {
        $connection = SmtpConnection::fromRow($this->row());

        $this->assertTrue($connection->hasPassword());
    }

    public function testHasPasswordIsFalseWithoutAPassword(): void
    {
        $connection = SmtpConnection::fromRow(['password' => null] + $this->row());

        $this->assertFalse($connection->hasPassword());
    }

    /**
     * @return array<string, mixed>
     */
    private function row(): array
    {
        return [
            'id' => '12',
            'organization_id' => '7',
            'name' => 'Primary relay',
            'host' => 'smtp.example.com',
            'port' => '587',
            'encryption' => 'tls',
            'username' => 'mailer',
            'password' => self::PASSWORD_BYTES,
            'from_address' => 'noreply@example.com',
            'active' => '1',
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => '2026-01-03 04:05:06',
        ];
    }
}
