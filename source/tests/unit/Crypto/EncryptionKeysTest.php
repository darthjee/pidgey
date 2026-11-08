<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Crypto;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Crypto\EncryptionKeys;
use Pidgey\Infra\Env;
use RuntimeException;

#[CoversClass(EncryptionKeys::class)]
#[UsesClass(Env::class)]
final class EncryptionKeysTest extends TestCase
{
    private const CURRENT = 'test-smtp-encryption-value-not-a-real-key-current';
    private const PREVIOUS = 'test-smtp-encryption-value-not-a-real-key-previous';
    private const OLDER = 'test-smtp-encryption-value-not-a-real-key-older';

    private const VARIABLES = ['SMTP_ENCRYPTION_KEY', 'PREVIOUS_SMTP_ENCRYPTION_KEYS'];

    /** @var array<string, string|false> */
    private array $saved = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->saved[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }
    }

    public function testKeyIdIsTheSpecifiedHkdfDerivation(): void
    {
        $this->assertSame(
            hash_hkdf('sha256', self::CURRENT, 8, 'pidgey:smtp-key-id:v1'),
            EncryptionKeys::keyIdOf(self::CURRENT),
        );
        $this->assertSame(8, strlen(EncryptionKeys::keyIdOf(self::CURRENT)));
    }

    public function testCurrentKeyIsDerivedAndNeverTheRawKey(): void
    {
        $keys = new EncryptionKeys(self::CURRENT);

        $this->assertSame(EncryptionKeys::keyIdOf(self::CURRENT), $keys->currentKeyId());
        $this->assertSame(
            hash_hkdf('sha256', self::CURRENT, 32, 'pidgey:smtp-password:v1'),
            $keys->currentCipherKey(),
        );
        $this->assertSame(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES, strlen($keys->currentCipherKey()));
        $this->assertNotSame(self::CURRENT, $keys->currentCipherKey());
        $this->assertNotSame($keys->currentCipherKey(), (new EncryptionKeys(self::PREVIOUS))->currentCipherKey());
    }

    public function testCipherKeysAreLookedUpByKeyId(): void
    {
        $keys = new EncryptionKeys(self::CURRENT, [self::PREVIOUS]);

        $this->assertSame($keys->currentCipherKey(), $keys->cipherKey(EncryptionKeys::keyIdOf(self::CURRENT)));
        $this->assertSame(
            (new EncryptionKeys(self::PREVIOUS))->currentCipherKey(),
            $keys->cipherKey(EncryptionKeys::keyIdOf(self::PREVIOUS)),
        );
        $this->assertNull($keys->cipherKey(EncryptionKeys::keyIdOf(self::OLDER)));
        $this->assertNull($keys->cipherKey(''));
    }

    public function testFromEnvReadsTheCurrentAndTrimmedPreviousKeys(): void
    {
        putenv('SMTP_ENCRYPTION_KEY=' . self::CURRENT);
        putenv('PREVIOUS_SMTP_ENCRYPTION_KEYS= ' . self::PREVIOUS . ' ,, ' . self::OLDER . ' ,');

        $keys = EncryptionKeys::fromEnv();

        $this->assertSame(EncryptionKeys::keyIdOf(self::CURRENT), $keys->currentKeyId());
        $this->assertNotNull($keys->cipherKey(EncryptionKeys::keyIdOf(self::PREVIOUS)));
        $this->assertNotNull($keys->cipherKey(EncryptionKeys::keyIdOf(self::OLDER)));
    }

    public function testFromEnvWithoutPreviousKeysHasOnlyTheCurrentKey(): void
    {
        putenv('SMTP_ENCRYPTION_KEY=' . self::CURRENT);

        $keys = EncryptionKeys::fromEnv();

        $this->assertNotNull($keys->cipherKey($keys->currentKeyId()));
        $this->assertNull($keys->cipherKey(EncryptionKeys::keyIdOf(self::PREVIOUS)));
    }

    public function testFromEnvReportsAMissingKeyWithoutAValue(): void
    {
        putenv('PREVIOUS_SMTP_ENCRYPTION_KEYS=' . self::PREVIOUS);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SMTP_ENCRYPTION_KEY');

        EncryptionKeys::fromEnv();
    }

    public function testDumpsHideEveryKey(): void
    {
        $keys = new EncryptionKeys(self::CURRENT, [self::PREVIOUS]);

        ob_start();
        var_dump($keys);
        $dump = (string) ob_get_clean() . print_r($keys, true);

        foreach ([self::CURRENT, self::PREVIOUS, $keys->currentCipherKey(), $keys->currentKeyId()] as $secret) {
            $this->assertStringNotContainsString($secret, $dump);
            $this->assertStringNotContainsString(bin2hex($secret), $dump);
        }

        $this->assertStringContainsString('[redacted]', $dump);
    }

    public function testSerializationIsRefused(): void
    {
        $this->expectException(LogicException::class);

        serialize(new EncryptionKeys(self::CURRENT, [self::PREVIOUS]));
    }

    public function testUnserializationIsRefused(): void
    {
        $class = EncryptionKeys::class;
        $payload = 'O:' . strlen($class) . ':"' . $class . '":0:{}';

        $this->expectException(LogicException::class);

        unserialize($payload);
    }
}
