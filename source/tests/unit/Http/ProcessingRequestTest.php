<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use ArrayObject;
use Countable;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;
use Pidgey\Http\Response;
use Pidgey\Infra\WallClock;
use SplObjectStorage;
use stdClass;

#[CoversClass(ProcessingRequest::class)]
#[UsesClass(ErrorFormatter::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(Request::class)]
#[UsesClass(Response::class)]
final class ProcessingRequestTest extends TestCase
{
    public function testExposesTheWrappedRequest(): void
    {
        $request = new Request(['method' => 'GET', 'uri' => '/']);

        $this->assertSame($request, (new ProcessingRequest($request))->request());
    }

    public function testDelegatesToTheRequest(): void
    {
        $processing = new ProcessingRequest(new Request(['method' => 'post', 'uri' => '/a%20b?c=d']));

        $this->assertSame('POST', $processing->method());
        $this->assertSame('/a%20b?c=d', $processing->uri());
        $this->assertSame('/a b', $processing->path());
        $this->assertSame('c=d', $processing->query());
    }

    public function testReturnsACapturedParam(): void
    {
        $processing = new ProcessingRequest(new Request(['method' => 'GET', 'uri' => '/a/7.json']), ['id' => '7']);

        $this->assertSame('7', $processing->param('id'));
    }

    public function testReturnsNullForAMissingParam(): void
    {
        $processing = new ProcessingRequest(new Request(['method' => 'GET', 'uri' => '/a/7.json']), ['id' => '7']);

        $this->assertNull($processing->param('name'));
    }

    public function testHasNoParamsByDefault(): void
    {
        $processing = new ProcessingRequest(new Request(['method' => 'GET', 'uri' => '/']));

        $this->assertNull($processing->param('id'));
    }

    public function testHasNoResponseInitially(): void
    {
        $processing = new ProcessingRequest(new Request(['method' => 'GET', 'uri' => '/']));

        $this->assertFalse($processing->hasResponse());
        $this->assertNull($processing->response());
    }

    public function testRespondSetsTheResponse(): void
    {
        $processing = new ProcessingRequest(new Request(['method' => 'GET', 'uri' => '/']));
        $response = new Response('early', 304);

        $processing->respond($response);

        $this->assertTrue($processing->hasResponse());
        $this->assertSame($response, $processing->response());
    }

    public function testSecondRespondThrows(): void
    {
        $processing = new ProcessingRequest(new Request(['method' => 'GET', 'uri' => '/']));
        $first = new Response('first');
        $processing->respond($first);

        try {
            $processing->respond(new Response('second'));
            $this->fail('Expected a LogicException.');
        } catch (LogicException) {
            $this->assertSame($first, $processing->response());
        }
    }

    public function testAttachedReturnsNullWhenNothingIsAttached(): void
    {
        $this->assertNull($this->processing()->attached(stdClass::class));
    }

    public function testAttachThenRequire(): void
    {
        $processing = $this->processing();
        $value = new stdClass();

        $processing->attach($value);

        $this->assertSame($value, $processing->require(stdClass::class));
        $this->assertSame($value, $processing->attached(stdClass::class));
    }

    public function testAttachesObjectsOfDifferentClasses(): void
    {
        $processing = $this->processing();
        $first = new stdClass();
        $second = new ArrayObject();

        $processing->attach($first);
        $processing->attach($second);

        $this->assertSame($first, $processing->require(stdClass::class));
        $this->assertSame($second, $processing->require(ArrayObject::class));
    }

    public function testAttachingASecondObjectOfTheSameClassThrows(): void
    {
        $processing = $this->processing();
        $first = new stdClass();
        $processing->attach($first);

        try {
            $processing->attach(new stdClass());
            $this->fail('Expected a LogicException');
        } catch (LogicException $error) {
            $this->assertSame('An object of class stdClass is already attached.', $error->getMessage());
        }

        $this->assertSame($first, $processing->require(stdClass::class));
    }

    public function testLooksUpByInterface(): void
    {
        $processing = $this->processing();
        $value = new ArrayObject();
        $processing->attach(new stdClass());
        $processing->attach($value);

        $this->assertSame($value, $processing->attached(Countable::class));
        $this->assertSame($value, $processing->require(Countable::class));
    }

    public function testTwoObjectsMatchingOneInterfaceThrow(): void
    {
        $processing = $this->processing();
        $processing->attach(new ArrayObject());
        $processing->attach(new SplObjectStorage());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('More than one attached object matches Countable.');

        $processing->attached(Countable::class);
    }

    public function testRequireWithTwoMatchesThrows(): void
    {
        $processing = $this->processing();
        $processing->attach(new ArrayObject());
        $processing->attach(new SplObjectStorage());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('More than one attached object matches Countable.');

        $processing->require(Countable::class);
    }

    public function testAttachedReturnsNullWhenNothingMatches(): void
    {
        $processing = $this->processing();
        $processing->attach(new stdClass());

        $this->assertNull($processing->attached(Countable::class));
    }

    public function testRequireOnAMissingObjectThrows(): void
    {
        $processing = $this->processing();
        $processing->attach(new stdClass());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No attached object matches Countable.');

        $processing->require(Countable::class);
    }

    public function testRequireOnAMissingObjectAnswers500(): void
    {
        $clock = new class implements WallClock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-25T12:00:00.000Z');
            }
        };
        $logged = [];
        $formatter = new ErrorFormatter($clock, static function (string $line) use (&$logged): void {
            $logged[] = $line;
        });

        try {
            $this->processing()->require(Countable::class);
            $this->fail('Expected a LogicException');
        } catch (LogicException $error) {
            $response = $formatter->format($error);
        }

        $this->assertSame(500, $response->status());
        $this->assertSame(
            '{"error":{"code":"INTERNAL_ERROR","message":"Internal server error"},'
            . '"statusCode":500,"timestamp":"2026-10-25T12:00:00.000Z"}',
            $response->body(),
        );
        $this->assertCount(1, $logged);
    }

    private function processing(): ProcessingRequest
    {
        return new ProcessingRequest(new Request(['method' => 'GET', 'uri' => '/']));
    }
}
