import {
  backoffDelay, MAX_BACKOFF_MS, shouldRetry, wait,
} from '../../lib/retry.js';

describe('retry', () => {
  describe('backoffDelay', () => {
    it('doubles from the base and caps at 8000 ms', () => {
      const delays = [1, 2, 3, 4, 5, 6].map((retry) => backoffDelay(500, retry));

      expect(delays).toEqual([500, 1000, 2000, 4000, 8000, 8000]);
    });

    it('keeps a zero base at zero', () => {
      expect([1, 2, 3].map((retry) => backoffDelay(0, retry))).toEqual([0, 0, 0]);
    });

    it('caps a base above the maximum on the first wait', () => {
      expect(backoffDelay(10000, 1)).toBe(MAX_BACKOFF_MS);
    });
  });

  describe('shouldRetry', () => {
    const failure = (code, statusCode, possiblySent = false) => ({ code, statusCode, possiblySent });

    [true, false].forEach((retryAmbiguous) => {
      describe(`with retryAmbiguous ${retryAmbiguous}`, () => {
        const retried = (error) => shouldRetry(error, retryAmbiguous);

        it('retries an unambiguous NETWORK_ERROR', () => {
          expect(retried(failure('NETWORK_ERROR', undefined, false))).toBeTrue();
        });

        it('retries an ambiguous NETWORK_ERROR only when opted in', () => {
          expect(retried(failure('NETWORK_ERROR', undefined, true))).toBe(retryAmbiguous);
        });

        it('retries SMTP_CONNECTION_FAILED', () => {
          expect(retried(failure('SMTP_CONNECTION_FAILED', 502))).toBeTrue();
        });

        it('retries SMTP_TEMPORARY_FAILURE', () => {
          expect(retried(failure('SMTP_TEMPORARY_FAILURE', 503))).toBeTrue();
        });

        it('retries SMTP_TIMEOUT only when opted in', () => {
          expect(retried(failure('SMTP_TIMEOUT', 504, true))).toBe(retryAmbiguous);
        });

        ['SMTP_REJECTED', 'SMTP_AUTH_FAILED', 'SMTP_TLS_FAILED'].forEach((code) => {
          it(`never retries ${code}, even on 503 or 504`, () => {
            expect(retried(failure(code, 502))).toBeFalse();
            expect(retried(failure(code, 503))).toBeFalse();
            expect(retried(failure(code, 504, true))).toBeFalse();
          });
        });

        [
          'SMTP_PROTOCOL_ERROR',
          'IDEMPOTENCY_KEY_IN_USE',
          'IDEMPOTENCY_KEY_REUSED',
          'INVALID_RESPONSE',
          'ABORTED',
          'CLIENT_CONFIGURATION',
        ].forEach((code) => {
          it(`never retries ${code}`, () => {
            expect(retried(failure(code, 503, false))).toBeFalse();
            expect(retried(failure(code, 504, true))).toBeFalse();
          });
        });

        it('does not retry an unknown code that is not SMTP_*', () => {
          expect(retried(failure('SOMETHING_NEW', 503))).toBeFalse();
          expect(retried(failure('SOMETHING_NEW', 504, true))).toBeFalse();
        });

        it('does not retry an error without a code', () => {
          expect(retried({ statusCode: 503 })).toBeFalse();
        });

        it('retries an unknown SMTP_* on 503', () => {
          expect(retried(failure('SMTP_NEW_THING', 503))).toBeTrue();
        });

        it('retries an unknown SMTP_* on 504 only when opted in', () => {
          expect(retried(failure('SMTP_NEW_THING', 504, true))).toBe(retryAmbiguous);
        });

        it('does not retry an unknown SMTP_* on 502 or 500', () => {
          expect(retried(failure('SMTP_NEW_THING', 502))).toBeFalse();
          expect(retried(failure('SMTP_NEW_THING', 500))).toBeFalse();
        });

        it('does not retry an unknown SMTP_* without a status', () => {
          expect(retried({ code: 'SMTP_NEW_THING' })).toBeFalse();
        });
      });
    });
  });

  describe('wait', () => {
    it('resolves after the time', async () => {
      await expectAsync(wait(1)).toBeResolved();
    });

    it('resolves with a signal that never fires', async () => {
      await expectAsync(wait(1, new AbortController().signal)).toBeResolved();
    });

    it('rejects when the signal is already aborted', async () => {
      const controller = new AbortController();
      controller.abort();

      await expectAsync(wait(60000, controller.signal)).toBeRejected();
    });

    it('rejects when the signal fires while waiting, without waiting out the timer', async () => {
      const controller = new AbortController();
      const started = Date.now();
      const waiting = wait(60000, controller.signal);
      controller.abort();

      await expectAsync(waiting).toBeRejected();
      expect(Date.now() - started).toBeLessThan(5000);
    });
  });
});
