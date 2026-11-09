<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Smtp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Smtp\EhloExtensions;
use Pidgey\Smtp\Reply;

#[CoversClass(EhloExtensions::class)]
#[UsesClass(Reply::class)]
final class EhloExtensionsTest extends TestCase
{
    public function testFirstLineIsTheGreetingNameNotAnExtension(): void
    {
        $extensions = $this->extensions(['STARTTLS greets you']);

        $this->assertFalse($extensions->has('STARTTLS'));
        $this->assertSame([], $extensions->authMechanisms());
    }

    public function testKeywordsAreCaseInsensitive(): void
    {
        $extensions = $this->extensions(['mail.example.com', 'starttls', 'SIZE 35882577', 'SmtpUtf8']);

        $this->assertTrue($extensions->has('STARTTLS'));
        $this->assertTrue($extensions->has('size'));
        $this->assertTrue($extensions->has('SMTPUTF8'));
        $this->assertFalse($extensions->has('PIPELINING'));
    }

    public function testAuthMechanisms(): void
    {
        $extensions = $this->extensions(['mail.example.com', 'AUTH plain LOGIN XOAUTH2']);

        $this->assertTrue($extensions->has('auth'));
        $this->assertSame(['PLAIN', 'LOGIN', 'XOAUTH2'], $extensions->authMechanisms());
    }

    public function testLegacyAuthFormAndSeveralLinesAreMerged(): void
    {
        $extensions = $this->extensions([
            'mail.example.com',
            'AUTH=LOGIN PLAIN',
            'auth CRAM-MD5  login',
        ]);

        $this->assertTrue($extensions->has('AUTH'));
        $this->assertSame(['LOGIN', 'PLAIN', 'CRAM-MD5'], $extensions->authMechanisms());
    }

    public function testLegacyAuthFormAlone(): void
    {
        $extensions = $this->extensions(['mail.example.com', 'AUTH=PLAIN']);

        $this->assertTrue($extensions->has('AUTH'));
        $this->assertSame(['PLAIN'], $extensions->authMechanisms());
    }

    public function testAuthWithoutMechanisms(): void
    {
        $extensions = $this->extensions(['mail.example.com', 'AUTH', 'AUTH=']);

        $this->assertTrue($extensions->has('AUTH'));
        $this->assertSame([], $extensions->authMechanisms());
    }

    public function testEmptyExtensionLinesAreIgnored(): void
    {
        $extensions = $this->extensions(['mail.example.com', '', '  ', '8BITMIME']);

        $this->assertTrue($extensions->has('8BITMIME'));
        $this->assertFalse($extensions->has(''));
    }

    public function testKeywordPrefixIsNotAMatch(): void
    {
        $extensions = $this->extensions(['mail.example.com', 'AUTHX PLAIN']);

        $this->assertFalse($extensions->has('AUTH'));
        $this->assertSame([], $extensions->authMechanisms());
    }

    /**
     * @param list<string> $lines
     */
    private function extensions(array $lines): EhloExtensions
    {
        return EhloExtensions::fromReply(new Reply(250, $lines));
    }
}
