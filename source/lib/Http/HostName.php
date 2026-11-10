<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * Tells whether a value is an ASCII hostname or an IP literal, as accepted for an SMTP host.
 *
 * Accepted: LDH hostnames (labels of letters, digits and hyphens, 1–63 characters, no leading or
 * trailing hyphen, at most 253 characters in total, no trailing dot), IPv4 literals, and IPv6
 * literals bare or in brackets. Rejected: Unicode (an IDN must be in punycode), whitespace,
 * control characters, IPv6 zone ids and all-numeric values that are not a valid IPv4.
 */
final class HostName
{
    private const MAX_LENGTH = 253;

    private const LABEL = '/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?$/D';

    /**
     * Tells whether the value is a valid hostname or IP literal.
     *
     * @param string $value Candidate host, already trimmed.
     *
     * @return boolean
     */
    public static function isValid(string $value): bool
    {
        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            return self::isIpv6(substr($value, 1, -1));
        }

        if (str_contains($value, ':')) {
            return self::isIpv6($value);
        }

        return self::isLdhOrIpv4($value);
    }

    /**
     * Tells whether the value is an IPv6 literal without a zone id.
     *
     * @param string $value Candidate literal, without brackets.
     *
     * @return boolean
     */
    private static function isIpv6(string $value): bool
    {
        if (str_contains($value, '%')) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /**
     * Tells whether the value is an LDH hostname, or a valid IPv4 when every label is numeric.
     *
     * @param string $value Candidate hostname.
     *
     * @return boolean
     */
    private static function isLdhOrIpv4(string $value): bool
    {
        if ($value === '' || strlen($value) > self::MAX_LENGTH) {
            return false;
        }

        $labels = explode('.', $value);
        $numeric = true;

        foreach ($labels as $label) {
            if (preg_match(self::LABEL, $label) !== 1) {
                return false;
            }

            $numeric = $numeric && ctype_digit($label);
        }

        if ($numeric) {
            return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        }

        return true;
    }
}
