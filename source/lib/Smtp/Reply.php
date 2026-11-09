<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

/**
 * One SMTP reply: its code and the text of each line (raw bytes, unsanitized).
 */
final readonly class Reply
{
    /** An enhanced status code (RFC 3463) at the start of a line text, then a space or the end. */
    private const ENHANCED_CODE_PATTERN = '/^([245])\.[0-9]{1,3}\.[0-9]{1,3}(?= |$)/D';

    /**
     * Builds the reply.
     *
     * @param integer            $code  Reply code, 200 to 599.
     * @param array<int, string> $lines Text of each line, without code and separator.
     */
    public function __construct(private int $code, private array $lines)
    {
    }

    /**
     * Returns the reply code.
     *
     * @return integer
     */
    public function code(): int
    {
        return $this->code;
    }

    /**
     * Returns the text of each line, without code and separator.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * Returns the line texts joined with one space; an enhanced status code shared by every
     * line, whose class matches the reply code's first digit, is kept once at the front.
     *
     * @return string
     */
    public function text(): string
    {
        $enhanced = $this->sharedEnhancedCode();

        if ($enhanced === null) {
            return self::join($this->lines);
        }

        $texts = array_map(
            static fn (string $line): string => ltrim(substr($line, strlen($enhanced)), ' '),
            $this->lines,
        );

        return self::join([$enhanced, ...$texts]);
    }

    /**
     * Returns the enhanced status code every line starts with, when its class matches the
     * reply code; null otherwise.
     *
     * @return string|null
     */
    private function sharedEnhancedCode(): ?string
    {
        $shared = null;

        foreach ($this->lines as $line) {
            if (preg_match(self::ENHANCED_CODE_PATTERN, $line, $match) !== 1) {
                return null;
            }

            if ($shared !== null && $match[0] !== $shared) {
                return null;
            }

            $shared = $match[0];
        }

        if ($shared === null || $shared[0] !== ((string) $this->code)[0]) {
            return null;
        }

        return $shared;
    }

    /**
     * Joins the non-empty texts with one space.
     *
     * @param array<int, string> $texts The texts.
     *
     * @return string
     */
    private static function join(array $texts): string
    {
        return implode(' ', array_filter($texts, static fn (string $text): bool => $text !== ''));
    }
}
