<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

use RuntimeException;
use Throwable;

/**
 * A stream operation failed: connect, TLS, timeout, close by the peer, or unexpected data.
 *
 * The message is fixed per kind and never carries sent or received bytes; a TLS failure may
 * carry the OpenSSL reason, for the log line only.
 */
final class StreamFailure extends RuntimeException
{
    private readonly ?string $reason;

    /**
     * Builds the failure.
     *
     * @param StreamFailureKind $kind     Why the operation failed.
     * @param string|null       $reason   OpenSSL reason; kept for `Tls` only.
     * @param Throwable|null    $previous Underlying error, if any.
     */
    public function __construct(
        private readonly StreamFailureKind $kind,
        ?string $reason = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($kind->message(), 0, $previous);
        $this->reason = $kind === StreamFailureKind::Tls ? $reason : null;
    }

    /**
     * Returns why the operation failed.
     *
     * @return StreamFailureKind
     */
    public function kind(): StreamFailureKind
    {
        return $this->kind;
    }

    /**
     * Returns the OpenSSL reason of a `Tls` failure, or null.
     *
     * @return string|null
     */
    public function reason(): ?string
    {
        return $this->reason;
    }
}
