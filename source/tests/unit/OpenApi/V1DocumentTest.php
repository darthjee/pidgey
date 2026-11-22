<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\OpenApi;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The committed `/v1` OpenAPI document (source/openapi/v1.json): valid OpenAPI 3.1 and the content
 * rules of specs/node-client/openapi.md.
 */
#[CoversNothing]
final class V1DocumentTest extends TestCase
{
    private const DOCUMENT = __DIR__ . '/../../../openapi/v1.json';

    private const FIXTURES = __DIR__ . '/../../fixtures/openapi';

    private const OPENAPI_SCHEMA = 'https://spec.openapis.org/oas/3.1/schema/2022-10-07';

    private const JSON_SCHEMA = 'https://json-schema.org/draft/2020-12/schema';

    private string $raw;

    /** @var array<string, mixed> */
    private array $document;

    protected function setUp(): void
    {
        $this->raw = (string) file_get_contents(self::DOCUMENT);
        $this->document = json_decode($this->raw, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testItIsAValidOpenApi31Document(): void
    {
        $validator = $this->validator();

        $this->assertValid($validator->validate(json_decode($this->raw), self::OPENAPI_SCHEMA));
    }

    public function testEverySchemaObjectIsAValidJsonSchema(): void
    {
        $validator = $this->validator();
        $schemas = $this->schemaObjects(json_decode($this->raw, false, 512, JSON_THROW_ON_ERROR));

        $this->assertNotEmpty($schemas);

        foreach ($schemas as $path => $schema) {
            $this->assertValid($validator->validate($schema, self::JSON_SCHEMA), $path);
        }
    }

    public function testTheValidationRejectsAnInvalidDocumentAndSchema(): void
    {
        $validator = $this->validator();

        $this->assertFalse($validator->validate((object) ['openapi' => '3.1.0'], self::OPENAPI_SCHEMA)->isValid());
        $this->assertFalse($validator->validate((object) ['type' => 'nonsense'], self::JSON_SCHEMA)->isValid());
    }

    public function testItIsOpenApi31WithTheContractVersion(): void
    {
        $this->assertSame('3.1.0', $this->document['openapi']);
        $this->assertSame('1.0.0', $this->document['info']['version']);
    }

    public function testItDescribesOnlyV1Paths(): void
    {
        $paths = array_keys($this->document['paths']);

        $this->assertSame(['/v1/messages', '/v1/openapi.json'], $paths);

        foreach ($paths as $path) {
            $this->assertStringStartsWith('/v1/', $path);
        }
    }

    public function testItHasNoServerWithARealHost(): void
    {
        $servers = $this->document['servers'] ?? [];

        $this->assertLessThanOrEqual(1, count($servers));

        foreach ($servers as $server) {
            $this->assertSame('/', $server['url']);
        }
    }

    public function testTheOpenApiRouteIsPublicAndTheSendRouteIsNot(): void
    {
        $paths = $this->document['paths'];

        $this->assertSame([], $paths['/v1/openapi.json']['get']['security']);
        $this->assertSame([], $paths['/v1/openapi.json']['head']['security']);
        $this->assertSame([['bearerAuth' => []]], $paths['/v1/messages']['post']['security']);
        $this->assertSame(['post'], array_keys($paths['/v1/messages']));
    }

    public function testTheSendOperationDocumentsEveryStatusOfTheSpec(): void
    {
        $responses = $this->document['paths']['/v1/messages']['post']['responses'];
        $statuses = array_map('strval', array_keys($responses));

        $this->assertSame(
            ['200', '400', '401', '403', '409', '413', '415', '422', '500', '502', '503', '504'],
            $statuses,
        );
        $this->assertArrayHasKey('Idempotent-Replayed', $responses['200']['headers']);
    }

    public function testTheDescriptionStatesTheRoutingNotFoundAndTheAdditiveOnlyRule(): void
    {
        $description = $this->document['info']['description'];

        $this->assertStringContainsString('NOT_FOUND', $description);
        $this->assertStringContainsString('404', $description);
        $this->assertStringContainsString('additive-only', $description);
    }

    public function testExampleTokensAndHostsAreAllPlaceholders(): void
    {
        preg_match_all('/pdg_[A-Za-z0-9_]+/', $this->raw, $tokens);

        foreach ($tokens[0] as $token) {
            $this->assertStringStartsWith('pdg_example', $token);
        }

        preg_match_all('/[A-Za-z0-9._%+-]+@([A-Za-z0-9.-]+)/', $this->raw, $emails);

        foreach ($emails[1] as $host) {
            $this->assertMatchesRegularExpression('/(^|\.)example\.com$/', $host);
        }

        preg_match_all('#https?://([A-Za-z0-9.-]+)#', $this->raw, $urls);

        foreach ($urls[1] as $host) {
            $this->assertMatchesRegularExpression('/(^|\.)example\.com$/', $host);
        }
    }

    public function testTheSecuritySchemeSaysNothingAboutStorageOrHashing(): void
    {
        $scheme = $this->document['components']['securitySchemes']['bearerAuth'];
        $description = strtolower($scheme['description']);

        $this->assertSame('http', $scheme['type']);
        $this->assertSame('bearer', $scheme['scheme']);
        $this->assertStringContainsString('send', $description);

        foreach (['sha', 'hash', 'bcrypt', 'stored', 'storage', 'database'] as $word) {
            $this->assertStringNotContainsString($word, $description);
        }
    }

    /**
     * A validator that knows the OpenAPI 3.1 and JSON Schema 2020-12 meta-schemas, without applying
     * `default` values (they would add properties to the validated document).
     */
    private function validator(): Validator
    {
        $validator = new Validator();
        $validator->parser()->setOption('allowDefaults', false);
        $validator->resolver()?->registerRaw($this->metaSchema());

        foreach (glob(self::FIXTURES . '/2020-12-*.json') ?: [] as $file) {
            $validator->resolver()?->registerRaw((string) file_get_contents($file));
        }

        return $validator;
    }

    /**
     * The OpenAPI 3.1 meta-schema with its Schema Object references opened to any schema (see the
     * README of the fixtures).
     */
    private function metaSchema(): string
    {
        $raw = (string) file_get_contents(self::FIXTURES . '/3.1-schema.json');

        return (string) preg_replace('/\{\s*"\$dynamicRef"\s*:\s*"#meta"\s*\}/', 'true', $raw);
    }

    /**
     * Every Schema Object of the document, by JSON pointer: the `components.schemas` entries and
     * every `schema` of a parameter, header or media type.
     *
     * @return array<string, mixed>
     */
    private function schemaObjects(mixed $node, string $path = ''): array
    {
        if (!is_object($node) && !is_array($node)) {
            return [];
        }

        $found = [];

        foreach (is_object($node) ? get_object_vars($node) : $node as $key => $value) {
            $child = $path . '/' . $key;

            if ($child === '/components/schemas' && is_object($value)) {
                foreach (get_object_vars($value) as $name => $schema) {
                    $found[$child . '/' . $name] = $schema;
                }
            } elseif ($key === 'schema') {
                $found[$child] = $value;
            } else {
                $found = [...$found, ...$this->schemaObjects($value, $child)];
            }
        }

        return $found;
    }

    private function assertValid(ValidationResult $result, string $message = ''): void
    {
        $error = $result->error();

        $this->assertTrue(
            $result->isValid(),
            $message . ' ' . ($error === null ? '' : json_encode((new ErrorFormatter())->format($error))),
        );
    }
}
