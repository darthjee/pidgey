# 02 — Host policy

Spec: `email/send-flow.md#host-check`, `email/smtp-connections.md#host-policy`.

- `Pidgey\Smtp\HostResolver` interface and `Pidgey\Smtp\SystemHostResolver`:
  - A records: `gethostbynamel($host) ?: []`;
  - AAAA records: `@dns_get_record($host, DNS_AAAA)`, keeping the `ipv6` entries; `false`
    or a warning counts as no records;
  - the result is deduplicated.
- `Pidgey\Smtp\HostCheck`: `allowed(string $ip)` / `denied()` / `unresolvable()`, plus
  `isAllowed()`, `isDenied()`, `ip()`.
- `Pidgey\Smtp\HostPolicy(HostResolver $resolver, bool $allowPrivate)`, `check(string $host): HostCheck`:
  - Resolution:
    - an IP literal (`filter_var($host, FILTER_VALIDATE_IP)`) is used as is;
    - otherwise the host is resolved;
    - an empty result is `unresolvable`.
  - Denial:
    - unless `$allowPrivate`, **any** address in a denied range is `denied`;
    - test the ranges with a small CIDR matcher on `inet_pton` bytes: IPv4 `/8`, `/12`,
      `/16`, `/10`, `/4` and `/32`; IPv6 `::1/128`, `::/128`, `fc00::/7`, `fe80::/10` and
      `ff00::/8`;
    - an IPv4-mapped IPv6 address (`::ffff:0:0/96`) is unwrapped and checked as IPv4;
    - also deny the IPv4 reserved ranges `240.0.0.0/4` (which covers `255.255.255.255`) and
      `0.0.0.0/8`.
  - Pick: the first IPv4, else the first IPv6. Nothing is re-resolved later.
- `Container::hostPolicy()`, with `allowPrivate` read as
  `Env::get('SMTP_ALLOW_PRIVATE_HOSTS') === 'true'`.

## Files to Change

- `source/lib/Smtp/HostResolver.php`, `SystemHostResolver.php`, `HostCheck.php`,
  `HostPolicy.php`: new.
- `source/lib/Container.php`: `hostPolicy()`, with an optional constructor override for
  tests, appended by name.
- `source/tests/unit/Smtp/HostPolicyTest.php` (stub resolver):
  - every denied range, at its edges (for example `172.15.255.255` allowed and
    `172.16.0.0` denied);
  - `169.254.169.254`;
  - mapped `::ffff:127.0.0.1` denied;
  - a public IPv4 / IPv6 allowed;
  - a mix of public and private addresses → denied;
  - the allow flag lets everything through;
  - IPv4 preferred;
  - an IP literal is never passed to the resolver;
  - an empty resolution → unresolvable.
- `source/tests/unit/ContainerTest.php`: the flag read (`true`, `TRUE`, unset).
- `source/tests/integration/Smtp/SystemHostResolverTest.php`: `localhost` resolves (hosts
  file).
