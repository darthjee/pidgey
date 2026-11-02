<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\Base64Url;

#[CoversClass(Base64Url::class)]
final class Base64UrlTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function encodings(): array
    {
        return [
            'empty' => ['', ''],
            'one byte' => ['f', 'Zg'],
            'two bytes' => ['fo', 'Zm8'],
            'three bytes' => ['foo', 'Zm9v'],
            'url-safe alphabet' => ["\xfb\xff\xbf", '-_-_'],
            'header' => ['{"alg":"HS256","typ":"JWT"}', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9'],
        ];
    }

    #[DataProvider('encodings')]
    public function testEncodesWithoutPadding(string $bytes, string $encoded): void
    {
        $this->assertSame($encoded, Base64Url::encode($bytes));
    }

    #[DataProvider('encodings')]
    public function testDecodesCanonicalInput(string $bytes, string $encoded): void
    {
        $this->assertSame($bytes, Base64Url::decode($encoded));
    }

    public function testRoundTripsRandomBytes(): void
    {
        for ($length = 1; $length < 40; $length++) {
            $bytes = random_bytes($length);

            $this->assertSame($bytes, Base64Url::decode(Base64Url::encode($bytes)));
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'padding' => ['Zg=='],
            'single padding' => ['Zm8='],
            'plus' => ['+/+/'],
            'slash' => ['ab/c'],
            'space' => ['Zm 9v'],
            'dot' => ['Zm9v.'],
            'trailing newline' => ["Zm9v\n"],
            'length 4n+1' => ['Zm9vZ'],
            'non-canonical trailing bits, one byte' => ['Zh'],
            'non-canonical trailing bits, two bytes' => ['Zm9'],
            'non-ascii' => ["Zm9\xc3\xa9"],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function testRejectsNonStrictInput(string $encoded): void
    {
        $this->assertNull(Base64Url::decode($encoded));
    }
}
