<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Smtp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Smtp\TlsContext;

#[CoversClass(TlsContext::class)]
final class TlsContextTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function peerNames(): array
    {
        return [
            'hostname' => ['smtp.example.com'],
            'ipv4' => ['192.0.2.10'],
            'ipv6' => ['2001:db8::7'],
        ];
    }

    #[DataProvider('peerNames')]
    public function testVerificationIsAlwaysOn(string $peerName): void
    {
        $ssl = TlsContext::options($peerName, null)['ssl'];

        $this->assertTrue($ssl['verify_peer']);
        $this->assertTrue($ssl['verify_peer_name']);
        $this->assertFalse($ssl['allow_self_signed']);
        $this->assertFalse($ssl['capture_peer_cert']);
        $this->assertSame($peerName, $ssl['peer_name']);
        $this->assertSame(
            STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
            $ssl['crypto_method'],
        );
    }

    public function testHostnameEnablesSni(): void
    {
        $ssl = TlsContext::options('smtp.example.com', null)['ssl'];

        $this->assertTrue($ssl['SNI_enabled']);
        $this->assertSame('smtp.example.com', $ssl['SNI_server_name']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ipLiterals(): array
    {
        return [
            'ipv4' => ['127.0.0.1'],
            'ipv6' => ['2001:db8::7'],
            'ipv6 loopback' => ['::1'],
        ];
    }

    #[DataProvider('ipLiterals')]
    public function testIpLiteralDisablesSni(string $address): void
    {
        $ssl = TlsContext::options($address, null)['ssl'];

        $this->assertFalse($ssl['SNI_enabled']);
        $this->assertArrayNotHasKey('SNI_server_name', $ssl);
    }

    public function testCaFileIsSetWhenGiven(): void
    {
        $ssl = TlsContext::options('smtp.example.com', '/etc/ssl/custom-ca.pem')['ssl'];

        $this->assertSame('/etc/ssl/custom-ca.pem', $ssl['cafile']);
    }

    public function testCaFileIsAbsentByDefault(): void
    {
        $this->assertArrayNotHasKey('cafile', TlsContext::options('smtp.example.com', null)['ssl']);
    }

    public function testOnlyTheSslWrapperIsConfigured(): void
    {
        $this->assertSame(['ssl'], array_keys(TlsContext::options('smtp.example.com', null)));
    }
}
