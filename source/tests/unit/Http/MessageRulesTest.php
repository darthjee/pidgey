<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Email\Message;
use Pidgey\Http\FieldRules;
use Pidgey\Http\HttpException;
use Pidgey\Http\MessageRules;
use Pidgey\Http\ValidationFailedException;

#[CoversClass(MessageRules::class)]
#[UsesClass(FieldRules::class)]
#[UsesClass(Message::class)]
#[UsesClass(ValidationFailedException::class)]
#[UsesClass(HttpException::class)]
final class MessageRulesTest extends TestCase
{
    public function testBuildsTheMessageFromAFullBody(): void
    {
        $message = MessageRules::forSend([
            'to' => ' user@example.com ',
            'subject' => ' Hello ',
            'text' => 'Hi!',
            'html' => '<p>Hi!</p>',
            'extra' => 1,
        ]);

        $this->assertEquals(new Message('user@example.com', ' Hello ', 'Hi!', '<p>Hi!</p>'), $message);
    }

    public function testATextOnlyBodyIsAccepted(): void
    {
        $message = MessageRules::forSend(['to' => 'a@b.co', 'subject' => 's', 'text' => 'Hi']);

        $this->assertEquals(new Message('a@b.co', 's', 'Hi', null), $message);
    }

    public function testAnHtmlOnlyBodyIsAccepted(): void
    {
        $message = MessageRules::forSend(['to' => 'a@b.co', 'subject' => 's', 'html' => '<p>Hi</p>', 'text' => null]);

        $this->assertEquals(new Message('a@b.co', 's', null, '<p>Hi</p>'), $message);
    }

    public function testAnEmptyTextIsTreatedAsNotSentWhenHtmlIsGiven(): void
    {
        $message = MessageRules::forSend(['to' => 'a@b.co', 'subject' => 's', 'text' => '', 'html' => '<p>x</p>']);

        $this->assertEquals(new Message('a@b.co', 's', null, '<p>x</p>'), $message);
    }

    public function testAcceptsASubjectOfExactly255MultibyteCharacters(): void
    {
        $subject = str_repeat('é', 255);

        $message = MessageRules::forSend(['to' => 'a@b.co', 'subject' => $subject, 'text' => 'x']);

        $this->assertSame($subject, $message->subject);
    }

    public function testRejectsASubjectOf256Characters(): void
    {
        $this->assertSame(
            ['subject must be shorter than or equal to 255 characters'],
            $this->detailsOf(['to' => 'a@b.co', 'subject' => str_repeat('é', 256), 'text' => 'x']),
        );
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string>         $details
     */
    #[DataProvider('invalidBodies')]
    public function testRejectsInvalidBodies(array $body, array $details): void
    {
        $this->assertSame($details, $this->detailsOf($body));
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function invalidBodies(): array
    {
        $valid = ['to' => 'a@b.co', 'subject' => 's', 'text' => 'x'];

        return [
            'missing to' => [['to' => null] + $valid, ['to should not be empty']],
            'blank to' => [['to' => '  '] + $valid, ['to should not be empty']],
            'to not a string' => [['to' => 5] + $valid, ['to must be a string']],
            'invalid to' => [['to' => 'not-an-email'] + $valid, ['to must be an email']],
            'non-ascii local part' => [['to' => 'josé@example.com'] + $valid, ['to must be an email']],
            'idn domain' => [['to' => 'user@exämple.com'] + $valid, ['to must be an email']],
            'to with a line break' => [
                ['to' => "a@b.co\r\nBcc: x@y.zz"] + $valid,
                ['to must be an email', 'to must not contain line breaks'],
            ],
            'to too long' => [
                ['to' => 'a@' . implode('.', array_fill(0, 4, str_repeat('b', 62))) . '.com'] + $valid,
                ['to must be an email', 'to must be shorter than or equal to 255 characters'],
            ],
            'missing subject' => [['subject' => null] + $valid, ['subject should not be empty']],
            'empty subject' => [['subject' => ''] + $valid, ['subject should not be empty']],
            'subject not a string' => [['subject' => ['x']] + $valid, ['subject must be a string']],
            'subject with LF' => [['subject' => "a\nb"] + $valid, ['subject must not contain control characters']],
            'subject with CR' => [['subject' => "a\rb"] + $valid, ['subject must not contain control characters']],
            'subject with TAB' => [['subject' => "a\tb"] + $valid, ['subject must not contain control characters']],
            'subject with DEL' => [['subject' => "a\x7Fb"] + $valid, ['subject must not contain control characters']],
            'neither text nor html' => [['text' => null] + $valid, ['text or html is required']],
            'both empty' => [['text' => '', 'html' => ''] + $valid, ['text or html is required']],
            'text not a string' => [['text' => 5] + $valid, ['text must be a string']],
            'html not a string' => [['html' => []] + $valid, ['html must be a string']],
            'everything wrong' => [
                [],
                ['to should not be empty', 'subject should not be empty', 'text or html is required'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<string>
     */
    private function detailsOf(array $body): array
    {
        try {
            MessageRules::forSend($body);
        } catch (ValidationFailedException $error) {
            return $error->details();
        }

        $this->fail('Expected a ValidationFailedException');
    }
}
