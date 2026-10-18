<?php

declare(strict_types=1);

namespace Pidgey\Infra\Migrations;

/**
 * Splits SQL text into statements on semicolons.
 *
 * Semicolons inside quoted strings or identifiers (single, double and backtick quotes, with
 * backslash and doubled-quote escapes) and inside comments (`-- `, `#` and block comments) do
 * not end a statement. Chunks holding only whitespace and comments are dropped.
 */
final class StatementSplitter
{
    /**
     * Splits the SQL text into its statements.
     *
     * @param string $sql SQL text.
     *
     * @return list<string> Trimmed, non-empty statements without the terminating semicolon.
     */
    public function split(string $sql): array
    {
        $statements = [];
        $current = '';
        $hasCode = false;
        $length = strlen($sql);
        $offset = 0;

        while ($offset < $length) {
            if ($sql[$offset] === ';') {
                $statements = $this->flush($statements, $current, $hasCode);
                $current = '';
                $hasCode = false;
                $offset++;
                continue;
            }

            [$end, $isCode] = $this->tokenEnd($sql, $offset);
            $current .= substr($sql, $offset, $end - $offset);
            $hasCode = $hasCode || $isCode;
            $offset = $end;
        }

        return $this->flush($statements, $current, $hasCode);
    }

    /**
     * Appends the current chunk to the statements when it holds code.
     *
     * @param string[] $statements Statements found so far.
     * @param string   $chunk      Text of the current chunk.
     * @param boolean  $hasCode    Whether the chunk holds anything but whitespace and comments.
     *
     * @return list<string>
     */
    private function flush(array $statements, string $chunk, bool $hasCode): array
    {
        if ($hasCode) {
            $statements[] = trim($chunk);
        }

        return $statements;
    }

    /**
     * Finds the end of the token starting at the offset.
     *
     * @param string  $sql    SQL text.
     * @param integer $offset Start of the token.
     *
     * @return array{int, bool} Offset just past the token, and whether the token is code.
     */
    private function tokenEnd(string $sql, int $offset): array
    {
        $char = $sql[$offset];

        if ($char === "'" || $char === '"' || $char === '`') {
            return [$this->quotedEnd($sql, $offset), true];
        }

        if ($this->startsLineComment($sql, $offset)) {
            $newline = strpos($sql, "\n", $offset);

            return [$newline === false ? strlen($sql) : $newline, false];
        }

        if (substr($sql, $offset, 2) === '/*') {
            $close = strpos($sql, '*/', $offset + 2);

            return [$close === false ? strlen($sql) : $close + 2, false];
        }

        return [$offset + 1, !ctype_space($char)];
    }

    /**
     * Tells whether a `#` or `-- ` comment starts at the offset.
     *
     * @param string  $sql    SQL text.
     * @param integer $offset Position to check.
     *
     * @return boolean
     */
    private function startsLineComment(string $sql, int $offset): bool
    {
        if ($sql[$offset] === '#') {
            return true;
        }

        if (substr($sql, $offset, 2) !== '--') {
            return false;
        }

        $after = $sql[$offset + 2] ?? '';

        return $after === '' || ctype_space($after);
    }

    /**
     * Finds the end of the quoted string or identifier starting at the offset.
     *
     * @param string  $sql    SQL text.
     * @param integer $offset Position of the opening quote.
     *
     * @return integer Offset just past the closing quote (or the end of the text).
     */
    private function quotedEnd(string $sql, int $offset): int
    {
        $quote = $sql[$offset];
        $length = strlen($sql);
        $position = $offset + 1;

        while ($position < $length) {
            $char = $sql[$position];

            if ($char === '\\' && $quote !== '`') {
                $position += 2;
                continue;
            }

            if ($char === $quote) {
                if (($sql[$position + 1] ?? '') !== $quote) {
                    return $position + 1;
                }

                $position++;
            }

            $position++;
        }

        return $length;
    }
}
