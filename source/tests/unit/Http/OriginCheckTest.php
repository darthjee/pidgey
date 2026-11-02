<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\BaseMiddleware;
use Pidgey\Http\ForbiddenException;
use Pidgey\Http\HttpException;
use Pidgey\Http\OriginCheck;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;
use Pidgey\Http\TrustedOrigins;
use Pidgey\Infra\Env;
use Pidgey\Infra\OriginParser;

#[CoversClass(OriginCheck::class)]
#[UsesClass(BaseMiddleware::class)]
#[UsesClass(Env::class)]
#[UsesClass(ForbiddenException::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(OriginParser::class)]
#[UsesClass(ProcessingRequest::class)]
#[UsesClass(Request::class)]
#[UsesClass(TrustedOrigins::class)]
final class OriginCheckTest extends TestCase
{
    private const TRUSTED = 'https://admin.example';

    private const FOREIGN = 'https://evil.example';

    /**
     * @return array<string, array{string}>
     */
    public static function safeMethods(): array
    {
        return ['GET' => ['GET'], 'HEAD' => ['HEAD'], 'OPTIONS' => ['OPTIONS']];
    }

    #[DataProvider('safeMethods')]
    public function testSafeMethodsPassWithAForeignOrigin(string $method): void
    {
        $this->assertPasses($method, ['Sec-Fetch-Site' => 'cross-site', 'Origin' => self::FOREIGN]);
        $this->assertPasses($method, ['Origin' => self::FOREIGN, 'Host' => 'pidgey.example']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function checkedMethods(): array
    {
        return [
            'POST' => ['POST'],
            'PUT' => ['PUT'],
            'PATCH' => ['PATCH'],
            'DELETE' => ['DELETE'],
            'unknown' => ['PROPFIND'],
        ];
    }

    #[DataProvider('checkedMethods')]
    public function testOtherMethodsAreChecked(string $method): void
    {
        $this->assertRejected($method, ['Sec-Fetch-Site' => 'cross-site', 'Origin' => self::FOREIGN]);
        $this->assertRejected($method, ['Origin' => self::FOREIGN, 'Host' => 'pidgey.example']);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function fetchSitePasses(): array
    {
        return [
            'same-origin' => [['Sec-Fetch-Site' => 'same-origin']],
            'none' => [['Sec-Fetch-Site' => 'none']],
            'same-origin with a foreign Origin' => [['Sec-Fetch-Site' => 'same-origin', 'Origin' => self::FOREIGN]],
            'cross-site with a trusted Origin' => [['Sec-Fetch-Site' => 'cross-site', 'Origin' => self::TRUSTED]],
            'same-site with a trusted Origin' => [['Sec-Fetch-Site' => 'same-site', 'Origin' => self::TRUSTED]],
        ];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('fetchSitePasses')]
    public function testSecFetchSitePasses(array $headers): void
    {
        $this->assertPasses('POST', $headers);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function fetchSiteRejections(): array
    {
        return [
            'cross-site with an untrusted Origin' => [['Sec-Fetch-Site' => 'cross-site', 'Origin' => self::FOREIGN]],
            'same-site with an untrusted Origin' => [['Sec-Fetch-Site' => 'same-site', 'Origin' => self::FOREIGN]],
            'cross-site without Origin' => [['Sec-Fetch-Site' => 'cross-site']],
            'upper-case Same-Origin' => [['Sec-Fetch-Site' => 'Same-Origin']],
            'padded same-origin' => [['Sec-Fetch-Site' => ' same-origin']],
            'empty' => [['Sec-Fetch-Site' => '']],
            'cross-site with Origin null' => [['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'null']],
            'same Host does not help' => [[
                'Sec-Fetch-Site' => 'cross-site',
                'Origin' => 'https://pidgey.example',
                'Host' => 'pidgey.example',
            ]],
        ];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('fetchSiteRejections')]
    public function testSecFetchSiteRejects(array $headers): void
    {
        $this->assertRejected('POST', $headers);
    }

    public function testNoHeadersPass(): void
    {
        $this->assertPasses('POST', []);
        $this->assertPasses('POST', ['Host' => 'pidgey.example']);
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function originPasses(): array
    {
        return [
            'trusted, foreign Host' => [self::TRUSTED, 'pidgey.example'],
            'trusted, no Host' => [self::TRUSTED, null],
            'same host and port' => ['http://pidgey.example:8080', 'pidgey.example:8080'],
            'https default port, Host without port' => ['https://pidgey.example', 'pidgey.example'],
            'https default port, Host with 443' => ['https://pidgey.example', 'pidgey.example:443'],
            'https explicit 443, Host without port' => ['https://pidgey.example:443', 'pidgey.example'],
            'http default port, Host with 80' => ['http://pidgey.example', 'pidgey.example:80'],
            'http default port, Host without port' => ['http://pidgey.example', 'pidgey.example'],
            'host case ignored' => ['https://Pidgey.EXAMPLE', 'pidgey.example'],
            'ipv6' => ['http://[::1]:8080', '[::1]:8080'],
            'ipv6 case ignored' => ['http://[::ABCD]', '[::abcd]'],
            'ip v4' => ['http://127.0.0.1:8080', '127.0.0.1:8080'],
        ];
    }

    #[DataProvider('originPasses')]
    public function testOriginOnlyPasses(string $origin, ?string $host): void
    {
        $headers = ['Origin' => $origin];

        if ($host !== null) {
            $headers['Host'] = $host;
        }

        $this->assertPasses('POST', $headers);
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function originRejections(): array
    {
        return [
            'foreign host' => [self::FOREIGN, 'pidgey.example'],
            'port mismatch' => ['http://pidgey.example:8080', 'pidgey.example:8081'],
            'default port mismatch' => ['https://pidgey.example', 'pidgey.example:80'],
            'http vs Host without port, other scheme port' => ['http://pidgey.example', 'pidgey.example:443'],
            'null' => ['null', 'pidgey.example'],
            'empty' => ['', 'pidgey.example'],
            'user info' => ['https://a@pidgey.example', 'pidgey.example'],
            'user and password' => ['https://a:b@pidgey.example', 'pidgey.example'],
            'trailing slash' => ['https://pidgey.example/', 'pidgey.example'],
            'path' => ['https://pidgey.example/x', 'pidgey.example'],
            'query' => ['https://pidgey.example?x=1', 'pidgey.example'],
            'fragment' => ['https://pidgey.example#x', 'pidgey.example'],
            'ftp scheme' => ['ftp://pidgey.example', 'pidgey.example'],
            'upper-case scheme' => ['HTTPS://pidgey.example', 'pidgey.example'],
            'no scheme' => ['pidgey.example', 'pidgey.example'],
            'no host' => ['https://', 'pidgey.example'],
            'empty port' => ['https://pidgey.example:', 'pidgey.example'],
            'port 0' => ['http://pidgey.example:0', 'pidgey.example:0'],
            'port above 65535' => ['http://pidgey.example:65536', 'pidgey.example:65536'],
            'trailing newline' => ["https://pidgey.example\n", 'pidgey.example'],
            'missing Host' => ['https://pidgey.example', null],
            'empty Host' => ['https://pidgey.example', ''],
            'Host with path' => ['https://pidgey.example', 'pidgey.example/x'],
            'Host with user info' => ['https://pidgey.example', 'a@pidgey.example'],
            'Host with trailing newline' => ['https://pidgey.example', "pidgey.example\n"],
            'Host with empty port' => ['https://pidgey.example', 'pidgey.example:'],
            'ipv6 port mismatch' => ['http://[::1]:8080', '[::1]:8081'],
            'unbracketed ipv6' => ['http://::1', '::1'],
            'suffix host' => ['https://evil-pidgey.example', 'pidgey.example'],
        ];
    }

    #[DataProvider('originRejections')]
    public function testOriginOnlyRejects(string $origin, ?string $host): void
    {
        $headers = ['Origin' => $origin];

        if ($host !== null) {
            $headers['Host'] = $host;
        }

        $this->assertRejected('POST', $headers);
    }

    public function testNullIsNeverTrusted(): void
    {
        $check = new OriginCheck(new TrustedOrigins(['null']));
        $request = new ProcessingRequest(new Request([
            'method' => 'POST',
            'uri' => '/x.json',
            'headers' => ['Origin' => 'null', 'Host' => 'null'],
        ]));

        $this->expectException(ForbiddenException::class);

        $check->processRequest($request);
    }

    public function testRejectionIsA403ForbiddenException(): void
    {
        try {
            $this->check('POST', ['Origin' => self::FOREIGN, 'Host' => 'pidgey.example']);
            $this->fail('Expected a ForbiddenException');
        } catch (ForbiddenException $error) {
            $this->assertSame(403, $error->status());
            $this->assertNull($error->errorCode());
            $this->assertSame('Cross-site request rejected', $error->errorMessage());
        }
    }

    public function testNeverResponds(): void
    {
        $request = new ProcessingRequest(new Request(['method' => 'POST', 'uri' => '/x.json']));

        (new OriginCheck(new TrustedOrigins([])))->processRequest($request);

        $this->assertFalse($request->hasResponse());
    }

    public function testDefaultsToTheTrustedOriginsFromTheEnvironment(): void
    {
        $saved = ['ALLOWED_ORIGINS' => getenv('ALLOWED_ORIGINS'), 'FRONTEND_BASE_URL' => getenv('FRONTEND_BASE_URL')];
        putenv('ALLOWED_ORIGINS=' . self::TRUSTED);

        try {
            $request = new ProcessingRequest(new Request([
                'method' => 'POST',
                'uri' => '/x.json',
                'headers' => ['Sec-Fetch-Site' => 'cross-site', 'Origin' => self::TRUSTED],
            ]));

            (new OriginCheck())->processRequest($request);

            $this->assertFalse($request->hasResponse());
        } finally {
            foreach ($saved as $name => $value) {
                putenv($value === false ? $name : "{$name}={$value}");
            }
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private function assertPasses(string $method, array $headers): void
    {
        $this->check($method, $headers);

        $this->addToAssertionCount(1);
    }

    /**
     * @param array<string, string> $headers
     */
    private function assertRejected(string $method, array $headers): void
    {
        try {
            $this->check($method, $headers);
        } catch (ForbiddenException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Expected a ForbiddenException');
    }

    /**
     * @param array<string, string> $headers
     */
    private function check(string $method, array $headers): void
    {
        $request = new ProcessingRequest(new Request(['method' => $method, 'uri' => '/x.json', 'headers' => $headers]));

        (new OriginCheck(new TrustedOrigins([self::TRUSTED])))->processRequest($request);
    }
}
