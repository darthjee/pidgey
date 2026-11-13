<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Smtp;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Smtp\HostCheck;
use Pidgey\Smtp\HostPolicy;
use Pidgey\Smtp\HostResolver;

#[CoversClass(HostPolicy::class)]
#[CoversClass(HostCheck::class)]
final class HostPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function deniedAddresses(): iterable
    {
        yield 'unspecified v4 start' => ['0.0.0.0'];
        yield 'unspecified v4 end' => ['0.255.255.255'];
        yield '10/8 start' => ['10.0.0.0'];
        yield '10/8 end' => ['10.255.255.255'];
        yield 'shared start' => ['100.64.0.0'];
        yield 'shared end' => ['100.127.255.255'];
        yield 'loopback start' => ['127.0.0.0'];
        yield 'loopback' => ['127.0.0.1'];
        yield 'loopback end' => ['127.255.255.255'];
        yield 'link-local start' => ['169.254.0.0'];
        yield 'metadata' => ['169.254.169.254'];
        yield 'link-local end' => ['169.254.255.255'];
        yield '172.16/12 start' => ['172.16.0.0'];
        yield '172.16/12 end' => ['172.31.255.255'];
        yield '192.168/16 start' => ['192.168.0.0'];
        yield '192.168/16 end' => ['192.168.255.255'];
        yield 'multicast start' => ['224.0.0.0'];
        yield 'multicast end' => ['239.255.255.255'];
        yield 'reserved start' => ['240.0.0.0'];
        yield 'broadcast' => ['255.255.255.255'];
        yield 'loopback v6' => ['::1'];
        yield 'unspecified v6' => ['::'];
        yield 'unique local start' => ['fc00::'];
        yield 'unique local end' => ['fdff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'];
        yield 'link-local v6 start' => ['fe80::'];
        yield 'link-local v6 end' => ['febf:ffff:ffff:ffff:ffff:ffff:ffff:ffff'];
        yield 'multicast v6 start' => ['ff00::'];
        yield 'multicast v6 end' => ['ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'];
        yield 'mapped loopback' => ['::ffff:127.0.0.1'];
        yield 'mapped metadata' => ['::ffff:169.254.169.254'];
        yield 'mapped private, hex form' => ['::ffff:a00:1'];
    }

    #[DataProvider('deniedAddresses')]
    public function testDeniesAResolvedAddressInADeniedRange(string $address): void
    {
        $check = $this->policy([$address])->check('smtp.example.com');

        $this->assertTrue($check->isDenied());
        $this->assertFalse($check->isAllowed());
    }

    #[DataProvider('deniedAddresses')]
    public function testDeniesAnIpLiteralInADeniedRange(string $address): void
    {
        $this->assertTrue($this->policy([])->check($address)->isDenied());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function allowedAddresses(): iterable
    {
        yield 'below 10/8' => ['9.255.255.255'];
        yield 'above 10/8' => ['11.0.0.0'];
        yield 'below shared' => ['100.63.255.255'];
        yield 'above shared' => ['100.128.0.0'];
        yield 'below loopback' => ['126.255.255.255'];
        yield 'above loopback' => ['128.0.0.0'];
        yield 'below link-local' => ['169.253.255.255'];
        yield 'above link-local' => ['169.255.0.0'];
        yield 'below 172.16/12' => ['172.15.255.255'];
        yield 'above 172.16/12' => ['172.32.0.0'];
        yield 'below 192.168/16' => ['192.167.255.255'];
        yield 'above 192.168/16' => ['192.169.0.0'];
        yield 'below multicast' => ['223.255.255.255'];
        yield 'public v4' => ['93.184.216.34'];
        yield 'public v6' => ['2001:db8::1'];
        yield 'below unique local' => ['fbff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'];
        yield 'above unique local' => ['fe00::'];
        yield 'above link-local v6' => ['fec0::'];
        yield '::2' => ['::2'];
        yield 'mapped public' => ['::ffff:93.184.216.34'];
    }

    #[DataProvider('allowedAddresses')]
    public function testAllowsAPublicAddress(string $address): void
    {
        $check = $this->policy([$address])->check('smtp.example.com');

        $this->assertTrue($check->isAllowed());
        $this->assertFalse($check->isDenied());
        $this->assertSame($address, $check->ip());
    }

    public function testDeniesWhenAnyAddressIsPrivate(): void
    {
        $check = $this->policy(['93.184.216.34', '2001:db8::1', '10.0.0.1'])->check('smtp.example.com');

        $this->assertTrue($check->isDenied());
    }

    public function testDeniesWhenAPrivateIpv6FollowsAPublicIpv4(): void
    {
        $this->assertTrue($this->policy(['93.184.216.34', '::1'])->check('smtp.example.com')->isDenied());
    }

    #[DataProvider('deniedAddresses')]
    public function testAllowsEveryAddressWhenPrivateHostsAreAllowed(string $address): void
    {
        $check = $this->policy([$address], true)->check('smtp.example.com');

        $this->assertTrue($check->isAllowed());
        $this->assertSame($address, $check->ip());
    }

    public function testPrefersTheFirstIpv4(): void
    {
        $check = $this->policy(['2001:db8::1', '93.184.216.34', '93.184.216.35'])->check('smtp.example.com');

        $this->assertSame('93.184.216.34', $check->ip());
    }

    public function testFallsBackToTheFirstIpv6(): void
    {
        $check = $this->policy(['2001:db8::1', '2001:db8::2'])->check('smtp.example.com');

        $this->assertSame('2001:db8::1', $check->ip());
    }

    public function testNeverResolvesAnIpLiteral(): void
    {
        $resolver = $this->createMock(HostResolver::class);
        $resolver->expects($this->never())->method('resolve');

        $check = (new HostPolicy($resolver, false))->check('93.184.216.34');

        $this->assertSame('93.184.216.34', $check->ip());
    }

    public function testNeverResolvesAnIpv6Literal(): void
    {
        $resolver = $this->createMock(HostResolver::class);
        $resolver->expects($this->never())->method('resolve');

        $this->assertSame('2001:db8::1', (new HostPolicy($resolver, false))->check('2001:db8::1')->ip());
    }

    public function testResolvesANameOnce(): void
    {
        $resolver = $this->createMock(HostResolver::class);
        $resolver->expects($this->once())->method('resolve')->with('smtp.example.com')->willReturn(['93.184.216.34']);

        (new HostPolicy($resolver, false))->check('smtp.example.com');
    }

    public function testAnEmptyResolutionIsUnresolvable(): void
    {
        $check = $this->policy([])->check('nowhere.invalid');

        $this->assertFalse($check->isAllowed());
        $this->assertFalse($check->isDenied());
    }

    public function testAnEmptyResolutionIsUnresolvableEvenWhenPrivateHostsAreAllowed(): void
    {
        $check = $this->policy([], true)->check('nowhere.invalid');

        $this->assertFalse($check->isAllowed());
        $this->assertFalse($check->isDenied());
    }

    public function testIgnoresAddressesThatAreNotIps(): void
    {
        $this->assertSame('93.184.216.34', $this->policy(['bogus', '93.184.216.34'])->check('smtp.example.com')->ip());
        $this->assertFalse($this->policy(['bogus'])->check('smtp.example.com')->isAllowed());
    }

    public function testTheIpOfADeniedCheckIsALogicError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('HostCheck: the host is not allowed');

        HostCheck::denied()->ip();
    }

    public function testTheIpOfAnUnresolvableCheckIsALogicError(): void
    {
        $this->expectException(LogicException::class);

        HostCheck::unresolvable()->ip();
    }

    /**
     * @param list<string> $addresses
     */
    private function policy(array $addresses, bool $allowPrivate = false): HostPolicy
    {
        $resolver = $this->createStub(HostResolver::class);
        $resolver->method('resolve')->willReturn($addresses);

        return new HostPolicy($resolver, $allowPrivate);
    }
}
