<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\HostName;

#[CoversClass(HostName::class)]
final class HostNameTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function validProvider(): array
    {
        return [
            'single label' => ['mailpit'],
            'hostname' => ['smtp.example.com'],
            'upper case' => ['SMTP.Example.COM'],
            'hyphen inside a label' => ['smtp-relay.example-mail.com'],
            'punycode' => ['smtp.xn--exmple-cua.de'],
            'digits in labels' => ['smtp1.example2.com'],
            'numeric label among others' => ['123.example.com'],
            'single character labels' => ['a.b.c'],
            '63-character label' => [str_repeat('a', 63) . '.example.com'],
            '253 characters in total' => [self::longHost(253)],
            'IPv4' => ['192.0.2.10'],
            'IPv4 loopback' => ['127.0.0.1'],
            'IPv4 bounds' => ['255.255.255.255'],
            'IPv6 bare' => ['::1'],
            'IPv6 bracketed' => ['[::1]'],
            'IPv6 full' => ['2001:db8::25'],
            'IPv6 bracketed full' => ['[2001:db8:0:0:0:0:0:25]'],
            'IPv4-mapped IPv6' => ['::ffff:10.0.0.1'],
            'IPv4-mapped IPv6 bracketed' => ['[::ffff:10.0.0.1]'],
        ];
    }

    #[DataProvider('validProvider')]
    public function testValid(string $host): void
    {
        $this->assertTrue(HostName::isValid($host));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidProvider(): array
    {
        return [
            'empty' => [''],
            'Unicode' => ['smtp.exämple.de'],
            'trailing dot' => ['smtp.example.com.'],
            'leading dot' => ['.example.com'],
            'empty label' => ['smtp..example.com'],
            'lone dot' => ['.'],
            'leading hyphen' => ['-smtp.example.com'],
            'trailing hyphen' => ['smtp-.example.com'],
            'underscore' => ['smtp_relay.example.com'],
            '64-character label' => [str_repeat('a', 64) . '.example.com'],
            '254 characters in total' => [self::longHost(254)],
            'space inside' => ['smtp example.com'],
            'leading space' => [' smtp.example.com'],
            'trailing space' => ['smtp.example.com '],
            'tab' => ["smtp\t.example.com"],
            'CR LF' => ["smtp.example.com\r\nRCPT TO:<x@example.com>"],
            'NUL' => ["smtp.example.com\0"],
            'DEL' => ["smtp.example.com\x7F"],
            'host with port' => ['smtp.example.com:587'],
            'URL' => ['smtp://smtp.example.com'],
            'path' => ['smtp.example.com/x'],
            'IPv4 out of range' => ['999.1.1.1'],
            'IPv4 with three parts' => ['10.0.1'],
            'IPv4 with five parts' => ['1.2.3.4.5'],
            'IPv4 leading zero' => ['010.0.0.1'],
            'all-numeric single label' => ['12345'],
            'IPv4 trailing dot' => ['127.0.0.1.'],
            'IPv6 zone id' => ['fe80::1%eth0'],
            'IPv6 bracketed zone id' => ['[fe80::1%eth0]'],
            'IPv6 malformed' => ['2001:db8:::25'],
            'IPv6 unclosed bracket' => ['[::1'],
            'IPv6 unopened bracket' => ['::1]'],
            'IPv6 bracketed with port' => ['[::1]:25'],
            'IPv4 in brackets' => ['[127.0.0.1]'],
            'hostname in brackets' => ['[smtp.example.com]'],
            'empty brackets' => ['[]'],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function testInvalid(string $host): void
    {
        $this->assertFalse(HostName::isValid($host));
    }

    /**
     * Builds an LDH hostname of the exact length, from 63-character labels.
     */
    private static function longHost(int $length): string
    {
        $labels = [];
        $remaining = $length;

        while ($remaining > 64) {
            $labels[] = str_repeat('a', 63);
            $remaining -= 64;
        }

        $labels[] = str_repeat('b', $remaining);
        $host = implode('.', $labels);

        self::assertSame($length, strlen($host));

        return $host;
    }
}
