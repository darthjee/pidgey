<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Smtp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Smtp\Reply;

#[CoversClass(Reply::class)]
final class ReplyTest extends TestCase
{
    public function testExposesCodeAndLines(): void
    {
        $reply = new Reply(250, ['mail.example.com', 'SIZE 1000']);

        $this->assertSame(250, $reply->code());
        $this->assertSame(['mail.example.com', 'SIZE 1000'], $reply->lines());
    }

    /**
     * @return array<string, array{integer, list<string>, string}>
     */
    public static function texts(): array
    {
        return [
            'single line' => [250, ['OK'], 'OK'],
            'empty text' => [250, [''], ''],
            'lines joined with one space' => [
                421,
                ['Service not available,', 'closing'],
                'Service not available, closing',
            ],
            'empty lines skipped' => [250, ['first', '', 'last'], 'first last'],
            'shared enhanced code kept once' => [
                550,
                ['5.1.1 No such', '5.1.1 user here'],
                '5.1.1 No such user here',
            ],
            'shared enhanced code alone on a line' => [550, ['5.1.1', '5.1.1 gone'], '5.1.1 gone'],
            'enhanced code class differs from the reply' => [
                550,
                ['4.1.1 No such', '4.1.1 user'],
                '4.1.1 No such 4.1.1 user',
            ],
            'enhanced code not on every line' => [
                550,
                ['5.1.1 No such', 'user'],
                '5.1.1 No such user',
            ],
            'different enhanced codes' => [
                550,
                ['5.1.1 No such', '5.1.2 user'],
                '5.1.1 No such 5.1.2 user',
            ],
            'enhanced code glued to text is not one' => [550, ['5.1.1x user'], '5.1.1x user'],
            'not an enhanced code' => [550, ['5.1 user'], '5.1 user'],
            'raw bytes kept' => [550, ["caf\xC3\xA9 \x00 a\rb"], "caf\xC3\xA9 \x00 a\rb"],
        ];
    }

    /**
     * @param list<string> $lines
     */
    #[DataProvider('texts')]
    public function testText(int $code, array $lines, string $expected): void
    {
        $this->assertSame($expected, (new Reply($code, $lines))->text());
    }

    public function testGmailMultiLineReply(): void
    {
        $reply = new Reply(550, [
            '5.1.1 The email account that you tried to reach does not exist. Please try',
            "5.1.1 double-checking the recipient's email address for typos or",
            '5.1.1 unnecessary spaces. Learn more at',
            '5.1.1  https://support.google.com/mail/?p=NoSuchUser 41be03b00d2f7-b2c - gsmtp',
        ]);

        $this->assertSame(
            '5.1.1 The email account that you tried to reach does not exist. Please try'
            . " double-checking the recipient's email address for typos or unnecessary spaces."
            . ' Learn more at https://support.google.com/mail/?p=NoSuchUser 41be03b00d2f7-b2c - gsmtp',
            $reply->text(),
        );
    }
}
