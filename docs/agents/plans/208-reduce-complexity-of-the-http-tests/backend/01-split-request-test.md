# Add the Request base test case and split RequestTest

Create `abstract class RequestTestCase extends TestCase` in `source/tests/support/RequestTestCase.php` (namespace `Pidgey\Tests\Support`) holding, verbatim except visibility: `$savedServer`, `$savedCookies`, `setUp()`, `tearDown()` and `jsonRequest()`.

Move the tests of `source/tests/unit/Http/RequestTest.php` into these classes in `source/tests/unit/Http/` (each `final`, extending `RequestTestCase`, with the original `CoversClass(Request::class)` and `UsesClass` attributes), then delete `RequestTest.php`:

| Class | Tests (and providers) |
|---|---|
| `RequestMethodTest` | testMethodIsUpperCased, testMethodDefaultsToGet, testMethodFallsBackToServerGlobal, testUriFallsBackToServerGlobal, testOptionsWinOverServerGlobals (5) |
| `RequestPathTest` | pathProvider + testPath, testPathDefaultsToRootWithoutUri, nulPathProvider + testPathRejectsADecodedNulByte, testNulByteInTheQueryIsNotRejectedByPath, testQueryIsRaw, testQueryIsEmptyWhenAbsent, testQueryIsEmptyWithoutUri, testQueryIsEmptyForUnparsableUri, rawUriProvider + testUriIsReturnedUnchanged, testUriDefaultsToRootWithoutUri (10) |
| `RequestHeaderTest` | headerNameProvider + testHeaderLookupIgnoresCase, unprefixedHeaderProvider + testContentHeadersMapWithoutPrefix, testContentTypeIgnoresAPrefixedKey, testMissingHeaderReturnsNull, testMissingHeaderReturnsTheDefault, testEmptyHeaderReturnsEmptyStringNotTheDefault, testHeaderValueIsRaw, testNonHeaderServerKeysAreNotHeaders, testHeadersOptionAcceptsHeaderNames, testHeadersOptionAcceptsServerKeys, testHeadersOptionReplacesServerHeaders, testHeadersOptionKeepsEmptyValues (12) |
| `RequestCookieTest` | testCookieIsReadFromTheGlobal, testMissingCookieReturnsNull, testMissingCookieReturnsTheDefault, testEmptyCookieReturnsEmptyStringNotTheDefault, testArrayCookieReturnsTheDefault, testCookiesOptionReplacesTheGlobal, testCookieLookupIsCaseSensitive (7) |
| `RequestBodyTest` | testBodyOptionReplacesTheInput, testBodyIsReadFromTheInputWhenNotGiven, anyContentTypeProvider + testEmptyBodyIsAnEmptyArrayWhateverTheContentType, jsonContentTypeProvider + testParsesJsonContentTypes, unsupportedMediaTypeProvider + testNonJsonMediaTypeIs415, testMediaTypeIsCheckedBeforeSize (6) |
| `RequestBodySizeTest` | oversizedContentLengthProvider + testContentLengthOverTheLimitIs413WithoutReading, testContentLengthOverTheLimitIs413EvenWithAnEmptyBody, acceptedContentLengthProvider + testContentLengthWithinTheLimitOrNotNumericIsIgnored, testBodyOverTheLimitWithoutContentLengthIs413, testBodyAtTheLimitIsAccepted (5) |
| `RequestJsonTest` | badRequestBodyProvider + testInvalidJsonOrNonObjectIs400, testParserErrorsCarryNoCustomMessage, testNestingWithinTheDepthLimitIsAccepted, testEmptyObjectIsAnEmptyArray, testNestedObjectsBecomeArrays, testUnknownFieldsAreKept, testJsonIsCached (7) |

Total: 52 tests.

## Files to Change
- `source/tests/support/RequestTestCase.php` — new abstract base.
- `source/tests/unit/Http/Request{Method,Path,Header,Cookie,Body,BodySize,Json}Test.php` — new.
- `source/tests/unit/Http/RequestTest.php` — deleted.
