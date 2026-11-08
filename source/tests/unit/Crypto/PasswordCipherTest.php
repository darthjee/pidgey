<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Crypto;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Crypto\DecryptionFailed;
use Pidgey\Crypto\EncryptionKeys;
use Pidgey\Crypto\PasswordCipher;

#[CoversClass(PasswordCipher::class)]
#[UsesClass(EncryptionKeys::class)]
#[UsesClass(DecryptionFailed::class)]
final class PasswordCipherTest extends TestCase
{
    private const CURRENT = 'test-smtp-encryption-value-not-a-real-key-current';
    private const PREVIOUS = 'test-smtp-encryption-value-not-a-real-key-previous';
    private const PASSWORD = 'distinctive-smtp-password-7f3a9c';
    private const ORGANIZATION = 12;
    private const CONNECTION = 345;

    /**
     * @return array<string, array{string}>
     */
    public static function passwords(): array
    {
        return [
            'ascii' => [self::PASSWORD],
            'one byte' => ['x'],
            'whitespace kept' => ["  spaced password \t"],
            'control characters' => ["nul\0bell\x07\r\nesc\x1b"],
            '255 four-byte characters' => [str_repeat("\u{1F426}", 255)],
        ];
    }

    #[DataProvider('passwords')]
    public function testRoundTripReturnsTheExactBytes(string $password): void
    {
        $cipher = $this->cipher();

        $stored = $cipher->encrypt($password, self::ORGANIZATION, self::CONNECTION);

        $this->assertSame($password, $cipher->decrypt($stored, self::ORGANIZATION, self::CONNECTION));
        $this->assertSame(strlen($password) + 49, strlen($stored));
        $this->assertStringNotContainsString($password, $stored);
    }

    public function testTheLargestPasswordFitsTheColumn(): void
    {
        $stored = $this->cipher()->encrypt(str_repeat("\u{1F426}", 255), self::ORGANIZATION, self::CONNECTION);

        $this->assertSame(1069, strlen($stored));
        $this->assertLessThanOrEqual(2048, strlen($stored));
    }

    public function testStoredBytesFollowTheLayout(): void
    {
        $stored = $this->cipher()->encrypt(self::PASSWORD, self::ORGANIZATION, self::CONNECTION);

        $this->assertSame("\x01", $stored[0]);
        $this->assertSame(EncryptionKeys::keyIdOf(self::CURRENT), substr($stored, 1, 8));

        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($stored, 33),
            'pidgey:smtp-connection:v1:12:345',
            substr($stored, 9, 24),
            hash_hkdf('sha256', self::CURRENT, 32, 'pidgey:smtp-password:v1'),
        );
        $this->assertSame(self::PASSWORD, $plain);
    }

    public function testEveryEncryptionUsesAFreshNonce(): void
    {
        $cipher = $this->cipher();

        $first = $cipher->encrypt(self::PASSWORD, self::ORGANIZATION, self::CONNECTION);
        $second = $cipher->encrypt(self::PASSWORD, self::ORGANIZATION, self::CONNECTION);

        $this->assertNotSame($first, $second);
        $this->assertNotSame(substr($first, 9, 24), substr($second, 9, 24));
    }

    public function testAnEmptyPasswordIsNeverEncrypted(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cipher()->encrypt('', self::ORGANIZATION, self::CONNECTION);
    }

    public function testTamperingWithAnyByteFails(): void
    {
        $cipher = $this->cipher();
        $stored = $cipher->encrypt(self::PASSWORD, self::ORGANIZATION, self::CONNECTION);

        for ($index = 0; $index < strlen($stored); $index++) {
            $tampered = $stored;
            $tampered[$index] = chr(ord($tampered[$index]) ^ 0x01);

            try {
                $cipher->decrypt($tampered, self::ORGANIZATION, self::CONNECTION);
                $this->fail("tampering with byte {$index} was not detected");
            } catch (DecryptionFailed $failure) {
                $this->assertSame(DecryptionFailed::MESSAGE, $failure->getMessage());
            }
        }
    }

    public function testAWrongKeyWithTheSameKeyIdFails(): void
    {
        $stored = $this->cipher()->encrypt(self::PASSWORD, self::ORGANIZATION, self::CONNECTION);
        // Forge stored bytes that name the other key's id, so the lookup succeeds but the tag fails.
        $forged = "\x01" . EncryptionKeys::keyIdOf(self::PREVIOUS) . substr($stored, 9);
        $cipher = new PasswordCipher(new EncryptionKeys(self::PREVIOUS));

        $failure = $this->decryptionFailure($cipher, $forged, self::ORGANIZATION, self::CONNECTION);

        $this->assertSame(bin2hex(EncryptionKeys::keyIdOf(self::PREVIOUS)), $failure->keyId);
    }

    public function testAnUnknownKeyIdFails(): void
    {
        $stored = $this->cipher()->encrypt(self::PASSWORD, self::ORGANIZATION, self::CONNECTION);
        $cipher = new PasswordCipher(new EncryptionKeys(self::PREVIOUS));

        $failure = $this->decryptionFailure($cipher, $stored, self::ORGANIZATION, self::CONNECTION);

        $this->assertSame(bin2hex(EncryptionKeys::keyIdOf(self::CURRENT)), $failure->keyId);
    }

    /**
     * @return array<string, array{integer, integer}>
     */
    public static function otherRows(): array
    {
        return [
            'other connection' => [self::ORGANIZATION, self::CONNECTION + 1],
            'other organization' => [self::ORGANIZATION + 1, self::CONNECTION],
            'swapped ids' => [self::CONNECTION, self::ORGANIZATION],
            'ambiguous concatenation' => [123, 45],
        ];
    }

    #[DataProvider('otherRows')]
    public function testAnAssociatedDataMismatchFails(int $organizationId, int $connectionId): void
    {
        $cipher = $this->cipher();
        $stored = $cipher->encrypt(self::PASSWORD, self::ORGANIZATION, self::CONNECTION);

        $failure = $this->decryptionFailure($cipher, $stored, $organizationId, $connectionId);

        $this->assertSame(bin2hex(EncryptionKeys::keyIdOf(self::CURRENT)), $failure->keyId);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedBytes(): array
    {
        return [
            'empty' => [''],
            'version only' => ["\x01"],
            '49 bytes' => ["\x01" . str_repeat("\xAA", 48)],
        ];
    }

    #[DataProvider('malformedBytes')]
    public function testShortBytesFail(string $stored): void
    {
        $failure = $this->decryptionFailure($this->cipher(), $stored, self::ORGANIZATION, self::CONNECTION);

        $this->assertNull($failure->keyId);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownVersions(): array
    {
        return [
            'zero' => ["\x00"],
            'two' => ["\x02"],
            'high' => ["\xFF"],
        ];
    }

    #[DataProvider('unknownVersions')]
    public function testAnUnknownVersionFails(string $version): void
    {
        $cipher = $this->cipher();
        $stored = $version . substr($cipher->encrypt(self::PASSWORD, self::ORGANIZATION, self::CONNECTION), 1);

        $failure = $this->decryptionFailure($cipher, $stored, self::ORGANIZATION, self::CONNECTION);

        $this->assertNull($failure->keyId);
    }

    public function testAValueEncryptedWithAPreviousKeyDecrypts(): void
    {
        $stored = (new PasswordCipher(new EncryptionKeys(self::PREVIOUS)))
            ->encrypt(self::PASSWORD, self::ORGANIZATION, self::CONNECTION);
        $cipher = new PasswordCipher(new EncryptionKeys(self::CURRENT, [self::PREVIOUS]));

        $this->assertSame(self::PASSWORD, $cipher->decrypt($stored, self::ORGANIZATION, self::CONNECTION));
        $this->assertFalse($cipher->isCurrent($stored));
    }

    public function testNewValuesAreEncryptedWithTheCurrentKey(): void
    {
        $cipher = new PasswordCipher(new EncryptionKeys(self::CURRENT, [self::PREVIOUS]));

        $stored = $cipher->encrypt(self::PASSWORD, self::ORGANIZATION, self::CONNECTION);

        $this->assertSame(EncryptionKeys::keyIdOf(self::CURRENT), substr($stored, 1, 8));
        $this->assertTrue($cipher->isCurrent($stored));
        $this->assertFalse($cipher->isCurrent(''));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownVersionsForIsCurrent(): array
    {
        return [
            'zero' => ["\x00"],
            'next version' => ["\x02"],
            'all bits' => ["\xff"],
        ];
    }

    #[DataProvider('unknownVersionsForIsCurrent')]
    public function testBytesWithAnUnknownVersionAreNeverCurrent(string $version): void
    {
        $cipher = $this->cipher();
        $stored = $cipher->encrypt(self::PASSWORD, self::ORGANIZATION, self::CONNECTION);
        $stored[0] = $version;

        $this->assertSame(EncryptionKeys::keyIdOf(self::CURRENT), substr($stored, 1, 8));
        $this->assertFalse($cipher->isCurrent($stored));
    }

    public function testNothingSecretLeaksIntoFailuresOrDumps(): void
    {
        $keys = new EncryptionKeys(self::CURRENT, [self::PREVIOUS]);
        $cipher = new PasswordCipher($keys);
        $stored = $cipher->encrypt(self::PASSWORD, self::ORGANIZATION, self::CONNECTION);
        $failure = $this->decryptionFailure($cipher, $stored, self::ORGANIZATION, self::CONNECTION + 1);

        ob_start();
        var_dump($cipher, $failure->keyId);
        $output = (string) ob_get_clean() . print_r($cipher, true) . $failure->getMessage()
            . $failure->getTraceAsString() . (string) $failure;

        foreach ([self::CURRENT, self::PREVIOUS, self::PASSWORD, $keys->currentCipherKey(), $stored] as $secret) {
            $this->assertStringNotContainsString($secret, $output);
            $this->assertStringNotContainsString(bin2hex($secret), $output);
            $this->assertStringNotContainsString(base64_encode($secret), $output);
        }
    }

    private function cipher(): PasswordCipher
    {
        return new PasswordCipher(new EncryptionKeys(self::CURRENT));
    }

    public function testSerializationIsRefused(): void
    {
        $this->expectException(LogicException::class);

        serialize(new PasswordCipher(new EncryptionKeys(self::CURRENT, [self::PREVIOUS])));
    }

    public function testUnserializationIsRefused(): void
    {
        $class = PasswordCipher::class;
        $payload = 'O:' . strlen($class) . ':"' . $class . '":0:{}';

        $this->expectException(LogicException::class);

        unserialize($payload);
    }

    private function decryptionFailure(
        PasswordCipher $cipher,
        string $stored,
        int $organizationId,
        int $connectionId,
    ): DecryptionFailed {
        try {
            $cipher->decrypt($stored, $organizationId, $connectionId);
        } catch (DecryptionFailed $failure) {
            $this->assertSame(DecryptionFailed::MESSAGE, $failure->getMessage());

            return $failure;
        }

        $this->fail('decryption did not fail');
    }
}
