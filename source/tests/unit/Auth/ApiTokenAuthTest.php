<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\ApiTokenAuth;

#[CoversClass(ApiTokenAuth::class)]
final class ApiTokenAuthTest extends TestCase
{
    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function headers(): array
    {
        return [
            'well formed' => ['Bearer pdg_abc123', 'pdg_abc123'],
            'scheme in any case' => ['bearer pdg_abc123', 'pdg_abc123'],
            'missing header' => [null, null],
            'empty header' => ['', null],
            'basic scheme' => ['Basic dXNlcjpwYXNz', null],
            'scheme only' => ['Bearer', null],
            'scheme and a space only' => ['Bearer ', null],
            'extra spaces between' => ['Bearer  pdg_abc123', null],
            'leading space' => [' Bearer pdg_abc123', null],
            'trailing space' => ['Bearer pdg_abc123 ', null],
            'trailing newline' => ["Bearer pdg_abc123\n", null],
            'two tokens' => ['Bearer pdg_abc 123', null],
            'token without a scheme' => ['pdg_abc123', null],
        ];
    }

    #[DataProvider('headers')]
    public function testBearerTokenReadsOnlyAWellFormedHeader(?string $header, ?string $expected): void
    {
        $this->assertSame($expected, ApiTokenAuth::bearerToken($header));
    }
}
