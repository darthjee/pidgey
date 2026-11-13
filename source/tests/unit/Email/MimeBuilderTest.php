<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Email;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Email\Message;
use Pidgey\Email\MimeBuilder;
use Pidgey\Tests\Support\FrozenWallClock;

#[CoversClass(MimeBuilder::class)]
final class MimeBuilderTest extends TestCase
{
    private const FROM = 'sender@mail.example.com';
    private const TO = 'to@example.org';

    private const FIRST_HEX = '01010101010101010101010101010101';
    private const SECOND_HEX = '02020202020202020202020202020202';

    public function testWritesTheHeadersInOrderForATextMessage(): void
    {
        $mime = $this->build(new Message(self::TO, 'Hello there', "Hi.\n", null));

        [$headers, $body] = self::split($mime);

        $this->assertSame([
            'Date: Sat, 07 Nov 2026 14:03:00 +0000',
            'From: ' . self::FROM,
            'To: ' . self::TO,
            'Subject: Hello there',
            'Message-ID: <' . self::FIRST_HEX . '@mail.example.com>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
        ], $headers);
        $this->assertSame("Hi.\r\n", $body);
    }

    public function testWritesTheDateInUtcWhateverTheClockZone(): void
    {
        $local = new DateTimeImmutable('2026-11-07 11:03:00', new DateTimeZone('America/Sao_Paulo'));
        $builder = new MimeBuilder(new FrozenWallClock($local), self::random());

        [$headers] = self::split($builder->build(self::FROM, new Message(self::TO, 'Hi', 'x', null)));

        $this->assertSame('Date: Sat, 07 Nov 2026 14:03:00 +0000', $headers[0]);
    }

    public function testDefaultsToRandomBytes(): void
    {
        $builder = new MimeBuilder(new FrozenWallClock(1_800_000_000));

        [$headers] = self::split($builder->build(self::FROM, new Message(self::TO, 'Hi', 'x', null)));

        $this->assertMatchesRegularExpression('/^Message-ID: <[0-9a-f]{32}@mail\.example\.com>$/', $headers[4]);
    }

    public function testWritesAnHtmlOnlyMessage(): void
    {
        [$headers, $body] = self::split($this->build(new Message(self::TO, 'Hi', null, '<p>a=b</p>')));

        $this->assertSame('Content-Type: text/html; charset=UTF-8', $headers[6]);
        $this->assertSame('Content-Transfer-Encoding: quoted-printable', $headers[7]);
        $this->assertCount(8, $headers);
        $this->assertSame('<p>a=3Db</p>', $body);
    }

    public function testWritesBothBodiesAsMultipartAlternativeTextFirst(): void
    {
        [$headers, $body] = self::split($this->build(new Message(self::TO, 'Hi', 'plain', '<b>rich</b>')));
        $boundary = '=_pidgey_' . self::SECOND_HEX;

        $this->assertSame('Content-Type: multipart/alternative; boundary="' . $boundary . '"', $headers[6]);
        $this->assertCount(7, $headers);
        $this->assertSame(
            "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n"
            . "\r\n"
            . "plain\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n"
            . "\r\n"
            . "<b>rich</b>\r\n"
            . "--{$boundary}--\r\n",
            $body,
        );
    }

    public function testKeepsAnAsciiSubjectAsIs(): void
    {
        [$headers] = self::split($this->build(new Message(self::TO, 'Re: =?not-encoded?= 100%', 'x', null)));

        $this->assertSame('Subject: Re: =?not-encoded?= 100%', $headers[3]);
    }

    public function testFoldsALongAsciiSubjectAt78(): void
    {
        $subject = trim(str_repeat('lorem ipsum ', 20));

        $mime = $this->build(new Message(self::TO, $subject, 'x', null));
        $lines = self::subjectLines($mime);

        $this->assertGreaterThan(1, count($lines));

        foreach ($lines as $index => $line) {
            $this->assertLessThanOrEqual(78, strlen($line));

            if ($index > 0) {
                $this->assertStringStartsWith(' ', $line);
            }
        }

        $this->assertSame('Subject: ' . $subject, implode('', $lines));
    }

    public function testKeepsAnOverlongAsciiWordWhole(): void
    {
        $word = str_repeat('x', 100);

        $lines = self::subjectLines($this->build(new Message(self::TO, "a {$word} b", 'x', null)));

        $this->assertSame(['Subject: a', " {$word}", ' b'], $lines);
    }

    public function testEncodesAUtf8SubjectAsEncodedWords(): void
    {
        $lines = self::subjectLines($this->build(new Message(self::TO, 'Olá, mundo', 'x', null)));

        $this->assertSame(['Subject: =?UTF-8?B?' . base64_encode('Olá, mundo') . '?='], $lines);
    }

    public function testNeverSplitsAUtf8CharacterAcrossEncodedWords(): void
    {
        // 38 ASCII bytes, then a 4-byte emoji that would cross the 39-byte first word.
        $subject = str_repeat('a', 38) . "\u{1F600}" . str_repeat('é', 40) . "\u{1F600}";

        $lines = self::subjectLines($this->build(new Message(self::TO, $subject, 'x', null)));
        $decoded = '';

        foreach ($lines as $index => $line) {
            $this->assertLessThanOrEqual(76, strlen($line), 'RFC 2047: encoded-word lines are at most 76');
            $word = $index === 0 ? substr($line, strlen('Subject: ')) : substr($line, 1);

            $this->assertLessThanOrEqual(75, strlen($word));
            $this->assertMatchesRegularExpression('/^=\?UTF-8\?B\?[A-Za-z0-9+\/=]+\?=$/', $word);

            $chunk = base64_decode(substr($word, 10, -2), true);
            $this->assertIsString($chunk);
            $this->assertSame(1, preg_match('//u', $chunk), 'each word holds whole UTF-8 characters');
            $decoded .= $chunk;
        }

        $this->assertSame(str_repeat('a', 38), base64_decode(substr($lines[0], strlen('Subject: =?UTF-8?B?'), -2)));
        $this->assertSame($subject, $decoded);
    }

    public function testEncodesEqualsSignsAndTrailingWhitespace(): void
    {
        [, $body] = self::split($this->build(new Message(self::TO, 'Hi', "a=b \nc\t\nmid dle ", null)));

        $this->assertSame("a=3Db=20\r\nc=09\r\nmid dle=20", $body);
    }

    public function testEncodesNonAsciiAndControlBytes(): void
    {
        [, $body] = self::split($this->build(new Message(self::TO, 'Hi', "é\x01\x7F", null)));

        $this->assertSame('=C3=A9=01=7F', $body);
    }

    public function testNormalizesLoneLfAndCrToCrlf(): void
    {
        [, $body] = self::split($this->build(new Message(self::TO, 'Hi', "one\ntwo\rthree\r\nfour", null)));

        $this->assertSame("one\r\ntwo\r\nthree\r\nfour", $body);
    }

    public function testNormalizesLineEndingsInEveryPart(): void
    {
        [, $body] = self::split($this->build(new Message(self::TO, 'Hi', "a\nb", "c\rd")));

        $this->assertStringContainsString("\r\na\r\nb\r\n--", $body);
        $this->assertStringContainsString("\r\nc\r\nd\r\n--", $body);
        $this->assertSame(0, preg_match('/\r(?!\n)|(?<!\r)\n/', $body));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function longBodies(): iterable
    {
        yield 'ascii' => [str_repeat('abcdefghij', 30)];
        yield 'non-ascii' => [str_repeat('é', 120)];
        yield 'mixed' => [str_repeat('a=é ', 60)];
        yield 'escape at the limit' => [str_repeat('a', 74) . '==' . str_repeat('b', 80)];
        yield 'exactly 76' => [str_repeat('a', 76)];
    }

    #[DataProvider('longBodies')]
    public function testKeepsQuotedPrintableLinesWithin76(string $text): void
    {
        [, $body] = self::split($this->build(new Message(self::TO, 'Hi', $text . "\n" . $text, null)));

        foreach (explode("\r\n", $body) as $line) {
            $this->assertLessThanOrEqual(76, strlen($line));
            $this->assertSame(0, preg_match('/=(?![0-9A-F]{2}|$)/', $line), 'no broken escape');
        }

        $this->assertSame($text . "\r\n" . $text, quoted_printable_decode($body));
    }

    public function testDoesNotBreakALineOfExactly76(): void
    {
        [, $body] = self::split($this->build(new Message(self::TO, 'Hi', str_repeat('a', 76), null)));

        $this->assertSame(str_repeat('a', 76), $body);
    }

    public function testKeepsAnEmptyBody(): void
    {
        [, $body] = self::split($this->build(new Message(self::TO, 'Hi', '', null)));

        $this->assertSame('', $body);
    }

    public function testUsesCrlfThroughout(): void
    {
        $mime = $this->build(new Message(self::TO, str_repeat('word ', 30), "a\nb", "c\nd"));

        $this->assertSame(0, preg_match('/\r(?!\n)|(?<!\r)\n/', $mime));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeSubjects(): iterable
    {
        yield 'CR' => ["a\rb"];
        yield 'LF' => ["a\nb"];
        yield 'NUL' => ["a\0b"];
        yield 'tab' => ["a\tb"];
        yield 'DEL' => ["a\x7Fb"];
    }

    #[DataProvider('unsafeSubjects')]
    public function testRefusesAControlCharacterInTheSubject(string $subject): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('MimeBuilder: control character in subject');

        $this->build(new Message(self::TO, $subject, 'x', null));
    }

    public function testRefusesASubjectThatIsNotUtf8(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('MimeBuilder: subject is not UTF-8');

        $this->build(new Message(self::TO, "caf\xE9", 'x', null));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeAddresses(): iterable
    {
        yield 'non-ascii' => ["josé@example.com"];
        yield 'CR' => ["a@example.com\rBcc: x@example.com"];
        yield 'LF' => ["a@example.com\nBcc: x@example.com"];
        yield 'NUL' => ["a\0@example.com"];
        yield 'DEL' => ["a\x7F@example.com"];
    }

    #[DataProvider('unsafeAddresses')]
    public function testRefusesAnUnsafeRecipient(string $address): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('MimeBuilder: unsafe address');

        $this->build(new Message($address, 'Hi', 'x', null));
    }

    #[DataProvider('unsafeAddresses')]
    public function testRefusesAnUnsafeSender(string $address): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('MimeBuilder: unsafe address');

        $this->builder()->build($address, new Message(self::TO, 'Hi', 'x', null));
    }

    public function testRefusesASenderWithoutDomain(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('MimeBuilder: address without domain');

        $this->builder()->build('sender@', new Message(self::TO, 'Hi', 'x', null));
    }

    private function build(Message $message): string
    {
        return $this->builder()->build(self::FROM, $message);
    }

    private function builder(): MimeBuilder
    {
        $clock = new FrozenWallClock(new DateTimeImmutable('2026-11-07 14:03:00', new DateTimeZone('UTC')));

        return new MimeBuilder($clock, self::random());
    }

    /**
     * Returns 16 bytes of 0x01 on the first call, of 0x02 on the second, and so on.
     */
    private static function random(): Closure
    {
        $calls = 0;

        return static function () use (&$calls): string {
            $calls++;

            return str_repeat(chr($calls), 16);
        };
    }

    /**
     * Splits a message into its unfolded header lines (folded lines kept apart) and its body.
     *
     * @return array{0: list<string>, 1: string}
     */
    private static function split(string $mime): array
    {
        [$head, $body] = explode("\r\n\r\n", $mime, 2);
        $headers = [];

        foreach (explode("\r\n", $head) as $line) {
            if (str_starts_with($line, ' ')) {
                $headers[count($headers) - 1] .= "\r\n" . $line;

                continue;
            }

            $headers[] = $line;
        }

        return [$headers, $body];
    }

    /**
     * Returns the physical lines of the Subject header.
     *
     * @return list<string>
     */
    private static function subjectLines(string $mime): array
    {
        [$headers] = self::split($mime);

        return explode("\r\n", $headers[3]);
    }
}
