<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

use LogicException;

/**
 * The result of the host check: allowed (with the checked IP to connect to), denied, or
 * unresolvable.
 */
final readonly class HostCheck
{
    /**
     * Builds the result; use the named constructors.
     *
     * @param boolean     $denied Whether an address is in a denied range.
     * @param string|null $ip     The checked IP, when allowed.
     */
    private function __construct(private bool $denied, private ?string $ip)
    {
    }

    /**
     * The host is allowed; the transport connects to the given IP.
     *
     * @param string $ip The checked IP.
     *
     * @return self
     */
    public static function allowed(string $ip): self
    {
        return new self(false, $ip);
    }

    /**
     * The host resolves to an address in a denied range.
     *
     * @return self
     */
    public static function denied(): self
    {
        return new self(true, null);
    }

    /**
     * The host resolves to no address.
     *
     * @return self
     */
    public static function unresolvable(): self
    {
        return new self(false, null);
    }

    /**
     * Tells whether the host is allowed.
     *
     * @return boolean
     */
    public function isAllowed(): bool
    {
        return $this->ip !== null;
    }

    /**
     * Tells whether the host was denied.
     *
     * @return boolean
     */
    public function isDenied(): bool
    {
        return $this->denied;
    }

    /**
     * Returns the checked IP to connect to.
     *
     * @return string
     *
     * @throws LogicException When the host is not allowed.
     */
    public function ip(): string
    {
        if ($this->ip === null) {
            throw new LogicException('HostCheck: the host is not allowed');
        }

        return $this->ip;
    }
}
