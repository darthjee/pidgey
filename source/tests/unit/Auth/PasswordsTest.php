<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\Passwords;

#[CoversClass(Passwords::class)]
final class PasswordsTest extends TestCase
{
    /** A valid register password generated per test, so no password literal is hardcoded. */
    private string $password;

    protected function setUp(): void
    {
        parent::setUp();

        $this->password = 'pw-' . bin2hex(random_bytes(12));
    }

    public function testDummyDigestNeedsNoRehash(): void
    {
        $this->assertFalse(password_needs_rehash(Passwords::DUMMY_DIGEST, PASSWORD_DEFAULT));
        $this->assertFalse((new Passwords())->needsRehash(Passwords::DUMMY_DIGEST));
    }

    public function testDummyDigestUsesTheDefaultAlgorithm(): void
    {
        $this->assertSame(PASSWORD_DEFAULT, password_get_info(Passwords::DUMMY_DIGEST)['algo']);
    }

    public function testVerifyWithoutADigestIsFalse(): void
    {
        $this->assertFalse((new Passwords())->verify($this->password, null));
        $this->assertFalse((new Passwords())->verify('', null));
    }

    public function testHashAndVerifyRoundTrip(): void
    {
        $passwords = new Passwords();

        $digest = $passwords->hash($this->password);

        $this->assertNotSame($this->password, $digest);
        $this->assertSame(PASSWORD_DEFAULT, password_get_info($digest)['algo']);
        $this->assertFalse($passwords->needsRehash($digest));
        $this->assertTrue($passwords->verify($this->password, $digest));
        $this->assertFalse($passwords->verify(strtoupper($this->password), $digest));
    }

    public function testAnOutdatedDigestNeedsRehash(): void
    {
        $digest = password_hash($this->password, PASSWORD_BCRYPT, ['cost' => 4]);

        $this->assertTrue((new Passwords())->needsRehash($digest));
        $this->assertTrue((new Passwords())->verify($this->password, $digest));
    }

    public function testVerifyRejectsAMalformedDigest(): void
    {
        $this->assertFalse((new Passwords())->verify($this->password, 'not-a-digest'));
    }

    public function testCanHashFollowsTheRegisterRules(): void
    {
        $passwords = new Passwords();

        $this->assertTrue($passwords->canHash($this->password));
        $this->assertTrue($passwords->canHash(str_repeat('a', Passwords::MAX_BYTES)));
        $this->assertTrue($passwords->canHash(str_repeat('é', 36)));
        $this->assertFalse($passwords->canHash(str_repeat('a', Passwords::MAX_BYTES + 1)));
        $this->assertFalse($passwords->canHash(str_repeat('é', 37)));
        $this->assertFalse($passwords->canHash("dummy\0value"));
        $this->assertFalse($passwords->canHash("\0"));
    }
}
