<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\SmtpConnectionPayload;
use Pidgey\Http\Timestamp;
use Pidgey\Model\SmtpConnection;
use Pidgey\Model\SmtpEncryption;

#[CoversClass(SmtpConnectionPayload::class)]
#[UsesClass(SmtpConnection::class)]
#[UsesClass(Timestamp::class)]
final class SmtpConnectionPayloadTest extends TestCase
{
    private const CANARY = 'canary-encrypted-bytes-7f3a';

    public function testItemShapeWithoutCredentials(): void
    {
        $payload = new SmtpConnectionPayload($this->connection(null, null, true));

        $this->assertSame(
            [
                'id' => 12,
                'name' => 'Mailpit',
                'host' => 'mailpit',
                'port' => 1025,
                'encryption' => 'none',
                'username' => null,
                'hasPassword' => false,
                'fromAddress' => 'noreply@example.com',
                'active' => true,
                'createdAt' => '2026-10-17T12:00:00.000Z',
                'updatedAt' => '2026-10-20T09:30:00.000Z',
            ],
            $payload->jsonSerialize(),
        );
    }

    public function testItemShapeWithCredentialsNeverCarriesThePassword(): void
    {
        $payload = new SmtpConnectionPayload($this->connection('relay-user', self::CANARY, false));
        $json = (string) json_encode($payload);

        $item = json_decode($json, true);
        $this->assertSame('relay-user', $item['username']);
        $this->assertTrue($item['hasPassword']);
        $this->assertFalse($item['active']);
        $this->assertArrayNotHasKey('password', $item);
        $this->assertArrayNotHasKey('encryptedPassword', $item);
        $this->assertArrayNotHasKey('organizationId', $item);
        $this->assertStringNotContainsString(self::CANARY, $json);
        $this->assertStringNotContainsString(self::CANARY, print_r($payload, true));
    }

    private function connection(?string $username, ?string $password, bool $active): SmtpConnection
    {
        $utc = new DateTimeZone('UTC');

        return new SmtpConnection(
            12,
            34,
            'Mailpit',
            'mailpit',
            1025,
            $password === null ? SmtpEncryption::None : SmtpEncryption::Tls,
            $username,
            $password,
            'noreply@example.com',
            $active,
            new DateTimeImmutable('2026-10-17 12:00:00', $utc),
            new DateTimeImmutable('2026-10-20 09:30:00', $utc),
        );
    }
}
