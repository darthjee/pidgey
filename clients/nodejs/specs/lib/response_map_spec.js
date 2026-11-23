import { inspect } from 'node:util';
import { mapResponse, MAX_BODY_BYTES, REDACTED } from '../../lib/response.js';
import { PidgeyError } from '../../lib/PidgeyError.js';

const TOKEN = 'pdg_secret_token';

const catchError = (fn) => {
  try {
    fn();
  } catch (error) {
    return error;
  }
  return undefined;
};

const respond = (status, body, headers = {}) => new Response(body, { status, headers });

describe('response', () => {
  describe('mapResponse', () => {
    describe('success', () => {
      it('returns the parsed JSON object unchanged', () => {
        const body = { status: 'sent', smtp: { code: 250, message: 'OK' }, extra: 1 };

        expect(mapResponse(respond(200, ''), JSON.stringify(body), TOKEN)).toEqual(body);
      });

      it('returns a body without smtp unchanged', () => {
        expect(mapResponse(respond(200, ''), '{"status":"sent"}', TOKEN)).toEqual({ status: 'sent' });
      });

      it('accepts another 2xx status', () => {
        expect(mapResponse(respond(201, ''), '{"a":1}', TOKEN)).toEqual({ a: 1 });
      });

      for (const text of ['', 'not json', 'null', '[1]', '"s"', '12']) {
        it(`maps a 2xx body of ${JSON.stringify(text)} to a possibly sent INVALID_RESPONSE`, () => {
          const error = catchError(() => mapResponse(respond(200, ''), text, TOKEN));

          expect(error).toBeInstanceOf(PidgeyError);
          expect(error.code).toBe('INVALID_RESPONSE');
          expect(error.statusCode).toBe(200);
          expect(error.possiblySent).toBe(true);
          expect(error.body).toBe(text);
        });
      }
    });

    describe('envelope', () => {
      const envelope = (extra = {}, statusCode) => JSON.stringify({
        error: { code: 'NO_SMTP_CONNECTION', message: 'No SMTP connection configured', ...extra },
        ...(statusCode === undefined ? {} : { statusCode }),
        timestamp: '2026-10-25T12:00:00.000Z',
      });

      it('copies code and message', () => {
        const error = catchError(() => mapResponse(respond(409, ''), envelope(), TOKEN));

        expect(error.code).toBe('NO_SMTP_CONNECTION');
        expect(error.message).toBe('No SMTP connection configured');
        expect(error.possiblySent).toBe(false);
      });

      it('uses the statusCode of the body', () => {
        const error = catchError(() => mapResponse(respond(409, ''), envelope({}, 418), TOKEN));

        expect(error.statusCode).toBe(418);
      });

      it('falls back to the HTTP status', () => {
        const error = catchError(() => mapResponse(respond(409, ''), envelope({}, '418'), TOKEN));

        expect(error.statusCode).toBe(409);
      });

      it('falls back to the HTTP status when the body has none', () => {
        expect(catchError(() => mapResponse(respond(401, ''), envelope(), TOKEN)).statusCode).toBe(401);
      });

      it('copies details and smtp', () => {
        const smtp = { code: 550, message: 'mailbox unavailable' };
        const error = catchError(() => mapResponse(
          respond(502, ''), envelope({ code: 'SMTP_REJECTED', details: ['a', 'b'], smtp }), TOKEN,
        ));

        expect(error.details).toEqual(['a', 'b']);
        expect(error.smtp).toEqual(smtp);
      });

      it('leaves details and smtp absent when the envelope has none', () => {
        const error = catchError(() => mapResponse(respond(409, ''), envelope(), TOKEN));

        expect(Object.hasOwn(error, 'details')).toBe(false);
        expect(Object.hasOwn(error, 'smtp')).toBe(false);
      });

      it('keeps the raw body, readable but hidden', () => {
        const text = envelope();
        const error = catchError(() => mapResponse(respond(409, ''), text, TOKEN));

        expect(error.body).toBe(text);
        expect(error.bodyTruncated).toBe(false);
        expect(JSON.stringify(error)).not.toContain('timestamp');
        expect(inspect(error)).not.toContain('timestamp');
      });
    });

    describe('possiblySent per code', () => {
      const cases = [
        ['SMTP_TIMEOUT', 504, true],
        ['SMTP_SOMETHING_NEW', 504, true],
        ['SMTP_PROTOCOL_ERROR', 502, true],
        ['IDEMPOTENCY_KEY_IN_USE', 409, true],
        ['IDEMPOTENCY_KEY_REUSED', 422, false],
        ['SMTP_CONNECTION_FAILED', 502, false],
        ['SMTP_TEMPORARY_FAILURE', 503, false],
        ['SMTP_SOMETHING_NEW', 503, false],
        ['SMTP_SOMETHING_NEW', 502, false],
        ['SMTP_REJECTED', 502, false],
        ['SMTP_AUTH_FAILED', 502, false],
        ['SMTP_TLS_FAILED', 502, false],
        ['VALIDATION_FAILED', 400, false],
        ['UNAUTHORIZED', 401, false],
        ['NO_SMTP_CONNECTION', 409, false],
        ['HTTP_413', 413, false],
        ['UNKNOWN_THING', 504, false],
      ];
      for (const [code, status, expected] of cases) {
        it(`${code} on ${status} is ${expected}`, () => {
          const text = JSON.stringify({ error: { code, message: 'm' }, statusCode: status });
          const error = catchError(() => mapResponse(respond(status, ''), text, TOKEN));

          expect(error.code).toBe(code);
          expect(error.possiblySent).toBe(expected);
        });
      }
    });

    describe('INVALID_RESPONSE', () => {
      const texts = {
        'a non-JSON body': '<html>Bad gateway</html>',
        'an empty body': '',
        'JSON without error': '{"message":"nope"}',
        'JSON that is not an object': '[1,2]',
        'null': 'null',
        'an error that is not an object': '{"error":"boom"}',
        'an error without code': '{"error":{"message":"m"}}',
        'an error without message': '{"error":{"code":"X"}}',
        'a numeric code': '{"error":{"code":1,"message":"m"}}',
        'a numeric message': '{"error":{"code":"X","message":1}}',
      };
      for (const [name, text] of Object.entries(texts)) {
        it(`is raised for ${name}, keeping the status`, () => {
          const error = catchError(() => mapResponse(respond(502, ''), text, TOKEN));

          expect(error.code).toBe('INVALID_RESPONSE');
          expect(error.statusCode).toBe(502);
          expect(error.possiblySent).toBe(false);
          expect(error.body).toBe(text);
        });
      }

      it('is raised for a 1xx status', () => {
        const error = catchError(() => mapResponse({ status: 199 }, '{}', TOKEN));

        expect(error.code).toBe('INVALID_RESPONSE');
        expect(error.possiblySent).toBe(false);
      });

      for (const status of [301, 302, 307, 399]) {
        it(`is raised for a ${status} redirect, even with an envelope`, () => {
          const text = '{"error":{"code":"X","message":"m"}}';
          const error = catchError(() => mapResponse({ status }, text, TOKEN));

          expect(error.code).toBe('INVALID_RESPONSE');
          expect(error.statusCode).toBe(status);
          expect(error.possiblySent).toBe(false);
          expect(error.message).toMatch(/redirect/);
        });
      }
    });

    describe('redaction and cut', () => {
      it('redacts the token from the fields copied out of the envelope', () => {
        const text = JSON.stringify({
          error: {
            code: 'SMTP_REJECTED',
            message: `echo ${TOKEN}`,
            details: [`d ${TOKEN}`],
            smtp: { code: 550, message: `s ${TOKEN}` },
          },
        });
        const error = catchError(() => mapResponse(respond(502, ''), text, TOKEN));

        expect(error.message).toBe(`echo ${REDACTED}`);
        expect(error.details).toEqual([`d ${REDACTED}`]);
        expect(error.smtp.message).toBe(`s ${REDACTED}`);
        expect(error.body).not.toContain(TOKEN);
      });

      it('redacts the token from a non-JSON body', () => {
        const error = catchError(() => mapResponse(respond(502, ''), `Authorization: Bearer ${TOKEN}`, TOKEN));

        expect(error.body).toBe(`Authorization: Bearer ${REDACTED}`);
      });

      it('cuts the body to 4 KiB and flags it', () => {
        const error = catchError(() => mapResponse(respond(502, ''), 'x'.repeat(10000), TOKEN));

        expect(error.body.length).toBe(MAX_BODY_BYTES);
        expect(error.bodyTruncated).toBe(true);
      });

      it('redacts before cutting, so a token straddling the cut cannot survive', () => {
        const text = 'x'.repeat(MAX_BODY_BYTES - 5) + TOKEN + 'tail';
        const error = catchError(() => mapResponse(respond(502, ''), text, TOKEN));

        expect(error.body).not.toContain('pdg_');
        expect(error.body.endsWith(REDACTED.slice(0, 5))).toBe(true);
      });

      it('does not cut a multi-byte character at the boundary', () => {
        const text = 'x'.repeat(MAX_BODY_BYTES - 1) + '€€';
        const error = catchError(() => mapResponse(respond(502, ''), text, TOKEN));

        expect(error.body).toBe('x'.repeat(MAX_BODY_BYTES - 1));
        expect(error.bodyTruncated).toBe(true);
      });

      it('keeps the cut on a success that is not JSON', () => {
        const error = catchError(() => mapResponse(respond(200, ''), 'y'.repeat(5000), TOKEN));

        expect(error.bodyTruncated).toBe(true);
      });
    });
  });
});
