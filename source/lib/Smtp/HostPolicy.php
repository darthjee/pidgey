<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

/**
 * The send-time host check (`email/send-flow.md#host-check`): resolves the host once, refuses
 * any address in a denied range (unless private hosts are allowed) and picks the IP to
 * connect to, IPv4 first.
 *
 * - An IP literal is checked as is, never resolved.
 * - An IPv4-mapped IPv6 address (`::ffff:a.b.c.d`) is checked as its IPv4 address.
 * - No address at all is unresolvable.
 */
final class HostPolicy
{
    /** Denied IPv4 ranges: unspecified, private, shared, loopback, link-local, multicast, reserved. */
    private const DENIED_IPV4 = [
        ['0.0.0.0', 8],
        ['10.0.0.0', 8],
        ['100.64.0.0', 10],
        ['127.0.0.0', 8],
        ['169.254.0.0', 16],
        ['172.16.0.0', 12],
        ['192.168.0.0', 16],
        ['224.0.0.0', 4],
        ['240.0.0.0', 4],
        ['255.255.255.255', 32],
    ];

    /** Denied IPv6 ranges: loopback, unspecified, unique local, link-local, multicast. */
    private const DENIED_IPV6 = [
        ['::1', 128],
        ['::', 128],
        ['fc00::', 7],
        ['fe80::', 10],
        ['ff00::', 8],
    ];

    /** The `::ffff:0:0/96` prefix of an IPv4-mapped IPv6 address. */
    private const MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xFF\xFF";

    private const IPV4_BYTES = 4;

    /**
     * Builds the policy.
     *
     * @param HostResolver $resolver     Resolves host names.
     * @param boolean      $allowPrivate Whether denied ranges are allowed
     *                                   (`SMTP_ALLOW_PRIVATE_HOSTS=true`).
     */
    public function __construct(private readonly HostResolver $resolver, private readonly bool $allowPrivate)
    {
    }

    /**
     * Checks the host and picks the IP to connect to.
     *
     * @param string $host The connection's host: a name or a bracket-free IP literal.
     *
     * @return HostCheck
     */
    public function check(string $host): HostCheck
    {
        $addresses = [];

        foreach ($this->addresses($host) as $address) {
            $packed = @inet_pton($address);

            if ($packed !== false) {
                $addresses[$address] = $packed;
            }
        }

        if ($addresses === []) {
            return HostCheck::unresolvable();
        }

        if (!$this->allowPrivate) {
            foreach ($addresses as $packed) {
                if (self::isDenied($packed)) {
                    return HostCheck::denied();
                }
            }
        }

        return HostCheck::allowed(self::pick($addresses));
    }

    /**
     * Returns the literal itself, or the resolved addresses of a name.
     *
     * @param string $host The host.
     *
     * @return list<string>
     */
    private function addresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        return $this->resolver->resolve($host);
    }

    /**
     * Returns the first IPv4 address, else the first address.
     *
     * @param non-empty-array<string, string> $addresses The packed addresses, by address.
     *
     * @return string
     */
    private static function pick(array $addresses): string
    {
        foreach ($addresses as $address => $packed) {
            if (strlen($packed) === self::IPV4_BYTES) {
                return (string) $address;
            }
        }

        return (string) array_key_first($addresses);
    }

    /**
     * Tells whether a packed address is in a denied range; a mapped address is checked as
     * IPv4.
     *
     * @param string $packed The address, as `inet_pton()` bytes.
     *
     * @return boolean
     */
    private static function isDenied(string $packed): bool
    {
        if (str_starts_with($packed, self::MAPPED_PREFIX) && strlen($packed) === 16) {
            $packed = substr($packed, strlen(self::MAPPED_PREFIX));
        }

        $ranges = strlen($packed) === self::IPV4_BYTES ? self::DENIED_IPV4 : self::DENIED_IPV6;

        foreach ($ranges as [$network, $prefix]) {
            if (self::inRange($packed, (string) inet_pton($network), $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether a packed address is in a CIDR range of the same family.
     *
     * @param string  $packed  The address bytes.
     * @param string  $network The network bytes.
     * @param integer $prefix  The prefix length, in bits.
     *
     * @return boolean
     */
    private static function inRange(string $packed, string $network, int $prefix): bool
    {
        $bytes = intdiv($prefix, 8);

        if (strncmp($packed, $network, $bytes) !== 0) {
            return false;
        }

        $bits = $prefix % 8;

        if ($bits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $bits)) & 0xFF;

        return (ord($packed[$bytes]) & $mask) === (ord($network[$bytes]) & $mask);
    }
}
