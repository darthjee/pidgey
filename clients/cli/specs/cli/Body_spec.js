import { Readable } from 'node:stream';
import { UsageError } from '../../cli/Arguments.js';
import { readBody, STDIN_LIMIT } from '../../cli/Body.js';

/**
 * Builds a stdin stand-in.
 * @param {Iterable<Uint8Array> | AsyncIterable<Uint8Array>} chunks - What it yields.
 * @param {boolean} [isTTY] - Whether it claims to be a terminal.
 * @returns {Readable} The readable.
 */
function stdinOf(chunks, isTTY = false) {
  const stdin = Readable.from(chunks, { objectMode: false });
  stdin.isTTY = isTTY;
  return stdin;
}

/**
 * Builds a stdin stand-in that records whether it was iterated.
 * @param {boolean} [isTTY] - Whether it claims to be a terminal.
 * @returns {object} The stand-in; `iterated` is a spy.
 */
function untouchedStdin(isTTY = false) {
  return {
    isTTY,
    destroy: jasmine.createSpy('destroy'),
    iterated: jasmine.createSpy('iterated'),
    [Symbol.asyncIterator]() {
      this.iterated();
      return [][Symbol.iterator]();
    },
  };
}

const bytes = (text) => new TextEncoder().encode(text);
const failure = (promise) => promise.then(() => fail('expected a UsageError'), (error) => error);

describe('readBody', () => {
  let signal;
  let controller;

  beforeEach(() => {
    controller = new AbortController();
    signal = controller.signal;
  });

  describe('with a body flag', () => {
    it('uses --text and never touches stdin', async () => {
      const stdin = untouchedStdin();

      expect(await readBody({ text: 'hi' }, { stdin, signal })).toEqual({ text: 'hi' });
      expect(stdin.iterated).not.toHaveBeenCalled();
    });

    it('uses --html alone, with no text part, and never touches stdin', async () => {
      const stdin = untouchedStdin();

      const body = await readBody({ html: '<p>hi</p>' }, { stdin, signal });

      expect(body).toEqual({ html: '<p>hi</p>' });
      expect('text' in body).toBeFalse();
      expect(stdin.iterated).not.toHaveBeenCalled();
    });

    it('uses both parts', async () => {
      const body = await readBody({ text: 'a', html: 'b' }, { stdin: stdinOf([]), signal });

      expect(body).toEqual({ text: 'a', html: 'b' });
    });

    it('keeps an empty --text', async () => {
      expect(await readBody({ text: '' }, { stdin: stdinOf([]), signal })).toEqual({ text: '' });
    });

    it('does not complain about a TTY stdin', async () => {
      expect(await readBody({ text: 'hi' }, { stdin: stdinOf([], true), signal })).toEqual({ text: 'hi' });
    });
  });

  describe('without a body flag', () => {
    it('reads stdin as the plain-text body', async () => {
      const stdin = stdinOf([bytes('Hello, '), bytes('world')]);

      expect(await readBody({}, { stdin, signal })).toEqual({ text: 'Hello, world' });
    });

    it('sends an empty stdin as an empty text', async () => {
      expect(await readBody({}, { stdin: stdinOf([]), signal })).toEqual({ text: '' });
    });

    it('decodes a multi-byte character split across chunks', async () => {
      const euro = bytes('€');
      const stdin = stdinOf([euro.subarray(0, 1), euro.subarray(1)]);

      expect(await readBody({}, { stdin, signal })).toEqual({ text: '€' });
    });

    it('turns invalid UTF-8 into replacement characters', async () => {
      const stdin = stdinOf([new Uint8Array([0x61, 0xff, 0x62])]);

      expect(await readBody({}, { stdin, signal })).toEqual({ text: 'a�b' });
    });

    it('refuses a TTY stdin without reading it', async () => {
      const stdin = untouchedStdin(true);
      const error = await failure(readBody({}, { stdin, signal }));

      expect(error).toBeInstanceOf(UsageError);
      expect(error.showUsage).toBeFalse();
      expect(error.message).toContain('--text');
      expect(stdin.iterated).not.toHaveBeenCalled();
    });

    it('reports an unreadable stdin with its error code', async () => {
      const stdin = new Readable({
        read() {
          this.destroy(Object.assign(new Error('boom'), { code: 'EIO' }));
        },
      });
      const error = await failure(readBody({}, { stdin, signal }));

      expect(error).toBeInstanceOf(UsageError);
      expect(error.message).toBe('Cannot read stdin: EIO');
    });

    it('reports an unreadable stdin without an error code', async () => {
      const stdin = new Readable({
        read() {
          this.destroy(new Error('boom'));
        },
      });

      expect((await failure(readBody({}, { stdin, signal }))).message).toBe('Cannot read stdin');
    });
  });

  describe('the stdin cap', () => {
    it('accepts exactly 1 MiB', async () => {
      const stdin = stdinOf([new Uint8Array(STDIN_LIMIT).fill(0x61)]);
      const body = await readBody({}, { stdin, signal });

      expect(body.text.length).toBe(STDIN_LIMIT);
    });

    it('accepts exactly 1 MiB split in chunks', async () => {
      const half = STDIN_LIMIT / 2;
      const stdin = stdinOf([new Uint8Array(half).fill(0x61), new Uint8Array(half).fill(0x62)]);

      expect((await readBody({}, { stdin, signal })).text.length).toBe(STDIN_LIMIT);
    });

    it('rejects 1 MiB plus one byte, naming the limit', async () => {
      const stdin = stdinOf([new Uint8Array(STDIN_LIMIT + 1)]);
      const error = await failure(readBody({}, { stdin, signal }));

      expect(error).toBeInstanceOf(UsageError);
      expect(error.showUsage).toBeFalse();
      expect(error.message).toContain('1 MiB');
      expect(error.message).toContain(String(STDIN_LIMIT));
      expect(stdin.destroyed).toBeTrue();
    });

    it('counts bytes before decoding', async () => {
      // 600000 two-byte characters: 600000 characters but 1200000 bytes.
      const stdin = stdinOf([bytes('é'.repeat(600000))]);

      expect((await failure(readBody({}, { stdin, signal }))).message).toContain('1 MiB');
    });

    it('stops reading as soon as the input goes over the cap', async () => {
      let pulled = 0;
      async function* source() {
        for (let i = 0; i < 100; i += 1) {
          pulled += 1;
          yield new Uint8Array(65536);
        }
      }
      const stdin = stdinOf(source());
      await failure(readBody({}, { stdin, signal }));

      expect(pulled).toBeLessThan(40);
    });
  });

  describe('SIGINT while reading', () => {
    it('stops and resolves null when the signal aborts while stdin is idle', async () => {
      const stdin = new Readable({ read: () => undefined });
      stdin.isTTY = false;
      const pending = readBody({}, { stdin, signal });
      setImmediate(() => controller.abort());

      expect(await pending).toBeNull();
      expect(stdin.destroyed).toBeTrue();
    });

    it('resolves null without reading when the signal is already aborted', async () => {
      controller.abort();
      const stdin = untouchedStdin();

      expect(await readBody({}, { stdin, signal })).toBeNull();
      expect(stdin.iterated).not.toHaveBeenCalled();
    });

    it('removes its listener when the read ends', async () => {
      spyOn(signal, 'removeEventListener').and.callThrough();
      await readBody({}, { stdin: stdinOf([bytes('x')]), signal });

      expect(signal.removeEventListener).toHaveBeenCalledWith('abort', jasmine.any(Function));
    });
  });
});
