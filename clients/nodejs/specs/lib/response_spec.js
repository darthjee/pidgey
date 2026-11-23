import http from 'node:http';
import { gzipSync } from 'node:zlib';
import {
  cut, MAX_BODY_BYTES, MAX_RESPONSE_BYTES, readText, redact, REDACTED,
} from '../../lib/response.js';

const TOKEN = 'pdg_secret_token';

const streamOf = (chunks) => new ReadableStream({
  start(controller) {
    chunks.forEach((chunk) => controller.enqueue(chunk));
    controller.close();
  },
});

const respond = (status, body, headers = {}) => new Response(body, { status, headers });

describe('response', () => {
  describe('readText', () => {
    it('reads a small body', async () => {
      expect(await readText(respond(200, 'hello'))).toEqual({ text: 'hello' });
    });

    it('reads an empty body as an empty text', async () => {
      expect(await readText(respond(204, null))).toEqual({ text: '' });
    });

    it('decodes a multi-byte character split across chunks', async () => {
      const bytes = new TextEncoder().encode('a€b');
      const response = new Response(streamOf([bytes.subarray(0, 2), bytes.subarray(2)]));

      expect(await readText(response)).toEqual({ text: 'a€b' });
    });

    it('replaces invalid UTF-8 with replacement characters', async () => {
      const response = new Response(streamOf([new Uint8Array([0x61, 0xff, 0x62])]));

      expect(await readText(response)).toEqual({ text: 'a�b' });
    });

    it('flushes a truncated multi-byte sequence at the end', async () => {
      const response = new Response(streamOf([new Uint8Array([0x61, 0xe2, 0x82])]));

      expect((await readText(response)).text.startsWith('a')).toBe(true);
    });

    it('accepts exactly the cap', async () => {
      const result = await readText(respond(200, 'a'.repeat(MAX_RESPONSE_BYTES)));

      expect(result.text.length).toBe(MAX_RESPONSE_BYTES);
    });

    it('refuses a Content-Length over the cap without reading', async () => {
      let pulled = false;
      const body = new ReadableStream({ pull() { pulled = true; } });
      const response = new Response(body, { headers: { 'content-length': String(MAX_RESPONSE_BYTES + 1) } });

      expect(await readText(response)).toEqual({ tooLarge: true });
      expect(pulled).toBe(false);
    });

    it('refuses a Content-Length over the cap on a response without a body', async () => {
      const response = { headers: new Headers({ 'content-length': '999999' }), body: null };

      expect(await readText(response)).toEqual({ tooLarge: true });
    });

    it('refuses a streamed body over the cap and cancels the read', async () => {
      let cancelled = false;
      const body = new ReadableStream({
        pull(controller) {
          controller.enqueue(new Uint8Array(40 * 1024));
        },
        cancel() { cancelled = true; },
      });

      expect(await readText(new Response(body))).toEqual({ tooLarge: true });
      expect(cancelled).toBe(true);
    });

    it('rejects when the signal is already aborted', async () => {
      const controller = new AbortController();
      controller.abort();

      await expectAsync(readText(respond(200, 'x'), controller.signal)).toBeRejected();
    });

    describe('with a gzip body', () => {
      let server;
      let port;

      beforeEach(async () => {
        server = http.createServer((req, res) => {
          const plain = Buffer.from('a'.repeat(MAX_RESPONSE_BYTES * 4));
          const small = gzipSync(plain);
          res.writeHead(200, { 'content-encoding': 'gzip', 'content-length': small.length });
          res.end(small);
        });
        await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
        port = server.address().port;
      });

      afterEach(async () => {
        server.closeAllConnections();
        await new Promise((resolve) => server.close(resolve));
      });

      it('caps the inflated bytes, not the compressed ones', async () => {
        const response = await fetch(`http://127.0.0.1:${port}/`);

        expect(await readText(response)).toEqual({ tooLarge: true });
      });
    });
  });

  describe('redact', () => {
    it('replaces every occurrence of the token', () => {
      expect(redact(`a ${TOKEN} b ${TOKEN}`, TOKEN)).toBe(`a ${REDACTED} b ${REDACTED}`);
    });

    it('leaves a text without the token unchanged', () => {
      expect(redact('nothing here', TOKEN)).toBe('nothing here');
    });

    it('matches the trimmed form as sent by fetch', () => {
      expect(redact(`echo ${TOKEN}`, ` ${TOKEN}\t`)).toBe(`echo ${REDACTED}`);
    });

    it('matches the configured form too', () => {
      expect(redact(`echo [ ${TOKEN} ]`, ` ${TOKEN} `)).toBe(`echo [${REDACTED}]`);
    });

    it('replaces the longer form first, leaving no remainder', () => {
      expect(redact(`x  ${TOKEN}  y`, `  ${TOKEN}  `)).toBe(`x${REDACTED}y`);
    });

    it('does not treat the token as a pattern', () => {
      expect(redact('a.c abc', 'a.c')).toBe(`${REDACTED} abc`);
    });
  });

  describe('cut', () => {
    it('keeps a short text', () => {
      expect(cut('hello')).toEqual({ body: 'hello', truncated: false });
    });

    it('keeps a text of exactly 4 KiB', () => {
      const text = 'a'.repeat(MAX_BODY_BYTES);

      expect(cut(text)).toEqual({ body: text, truncated: false });
    });

    it('cuts a longer text to 4 KiB', () => {
      const result = cut('a'.repeat(MAX_BODY_BYTES + 10));

      expect(result.body.length).toBe(MAX_BODY_BYTES);
      expect(result.truncated).toBe(true);
    });

    it('never splits a multi-byte character', () => {
      const text = 'a' + '€'.repeat(2000);
      const result = cut(text);

      expect(result.truncated).toBe(true);
      expect(result.body).toBe('a' + '€'.repeat(1365));
      expect(result.body).not.toContain('�');
      expect(new TextEncoder().encode(result.body).length).toBeLessThanOrEqual(MAX_BODY_BYTES);
    });

    it('never splits an astral character', () => {
      const result = cut('😀'.repeat(2000));

      expect(result.body).toBe('😀'.repeat(1024));
    });
  });
});
