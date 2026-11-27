import { execFileSync } from 'node:child_process';
import { chmod, mkdtemp, rm, symlink, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { UsageError } from '../../cli/Arguments.js';
import { resolveCredentials } from '../../cli/Credentials.js';

describe('resolveCredentials', () => {
  let dir;
  let stderr;

  const withFile = async (name, content, mode = 0o600) => {
    const path = join(dir, name);
    await writeFile(path, content, { mode });
    await chmod(path, mode);
    return path;
  };
  const resolve = (args, env = {}, platform = 'linux') => resolveCredentials(
    args,
    { env, stderr, platform },
  );
  const failure = (promise) => promise.then(
    () => fail('expected a UsageError'),
    (error) => error,
  );

  beforeEach(async () => {
    dir = await mkdtemp(join(tmpdir(), 'pidgey-cli-'));
    stderr = { text: '', write(chunk) { this.text += chunk; } };
  });

  afterEach(async () => {
    await rm(dir, { recursive: true, force: true });
  });

  describe('sources', () => {
    it('takes the token and the URL from the environment', async () => {
      const env = { PIDGEY_TOKEN: 'env-token', PIDGEY_URL: 'https://env.example.com' };

      expect(await resolve({}, env)).toEqual({ token: 'env-token', baseUrl: 'https://env.example.com' });
    });

    it('prefers --token-file over PIDGEY_TOKEN', async () => {
      const tokenFile = await withFile('token', 'file-token');
      const env = { PIDGEY_TOKEN: 'env-token', PIDGEY_URL: 'https://env.example.com' };

      expect((await resolve({ tokenFile }, env)).token).toBe('file-token');
    });

    it('prefers --url over PIDGEY_URL', async () => {
      const env = { PIDGEY_TOKEN: 't', PIDGEY_URL: 'https://env.example.com' };

      expect((await resolve({ url: 'https://flag.example.com' }, env)).baseUrl).toBe('https://flag.example.com');
    });

    it('rejects a missing token', async () => {
      const error = await failure(resolve({}, { PIDGEY_URL: 'https://x.example.com' }));

      expect(error).toBeInstanceOf(UsageError);
      expect(error.showUsage).toBeFalse();
      expect(error.message).toContain('PIDGEY_TOKEN');
    });

    it('rejects an empty PIDGEY_TOKEN as missing', async () => {
      const error = await failure(resolve({}, { PIDGEY_TOKEN: '', PIDGEY_URL: 'https://x.example.com' }));

      expect(error.message).toContain('Missing token');
    });

    it('rejects a missing URL', async () => {
      const error = await failure(resolve({}, { PIDGEY_TOKEN: 't' }));

      expect(error).toBeInstanceOf(UsageError);
      expect(error.showUsage).toBeFalse();
      expect(error.message).toContain('PIDGEY_URL');
    });
  });

  describe('token file', () => {
    const url = { url: 'https://x.example.com' };

    it('trims one trailing LF', async () => {
      const tokenFile = await withFile('token', 'abc\n');

      expect((await resolve({ tokenFile, ...url })).token).toBe('abc');
    });

    it('trims one trailing CRLF', async () => {
      const tokenFile = await withFile('token', 'abc\r\n');

      expect((await resolve({ tokenFile, ...url })).token).toBe('abc');
    });

    it('keeps other whitespace', async () => {
      const tokenFile = await withFile('token', ' abc ');

      expect((await resolve({ tokenFile, ...url })).token).toBe(' abc ');
    });

    it('rejects a second trailing newline as a control character', async () => {
      const tokenFile = await withFile('token', 'abc\n\n');

      expect((await failure(resolve({ tokenFile, ...url }))).message).toContain('control character');
    });

    it('accepts exactly 4096 bytes', async () => {
      const tokenFile = await withFile('token', 'a'.repeat(4096));

      expect((await resolve({ tokenFile, ...url })).token.length).toBe(4096);
    });

    it('rejects 4097 bytes', async () => {
      const tokenFile = await withFile('token', 'a'.repeat(4097));
      const error = await failure(resolve({ tokenFile, ...url }));

      expect(error).toBeInstanceOf(UsageError);
      expect(error.message).toContain('4 KiB');
      expect(error.message).toContain(tokenFile);
    });

    it('rejects a missing file, naming the path', async () => {
      const tokenFile = join(dir, 'missing');
      const error = await failure(resolve({ tokenFile, ...url }));

      expect(error).toBeInstanceOf(UsageError);
      expect(error.showUsage).toBeFalse();
      expect(error.message).toContain(tokenFile);
      expect(error.message).toContain('ENOENT');
    });

    it('rejects a file that cannot be opened (a symlink loop), naming the path', async () => {
      const tokenFile = join(dir, 'loop');
      await symlink(tokenFile, tokenFile);
      const error = await failure(resolve({ tokenFile, ...url }));

      expect(error).toBeInstanceOf(UsageError);
      expect(error.message).toContain(tokenFile);
      expect(error.message).toContain('ELOOP');
    });

    it('rejects an empty file', async () => {
      const tokenFile = await withFile('token', '');

      expect((await failure(resolve({ tokenFile, ...url }))).message).toContain('empty');
    });

    it('rejects a file with only a newline', async () => {
      const tokenFile = await withFile('token', '\n');

      expect((await failure(resolve({ tokenFile, ...url }))).message).toContain('empty');
    });

    it('rejects a whitespace-only file', async () => {
      const tokenFile = await withFile('token', '  \t ');

      expect((await failure(resolve({ tokenFile, ...url }))).message).toContain('empty');
    });

    for (const [name, content] of [['CR', 'xy\rz'], ['LF', 'xy\nz'], ['NUL', 'xy\u0000z'], ['tab', 'xy\tz']]) {
      it(`rejects a ${name} inside the token, naming the path and not the content`, async () => {
        const tokenFile = await withFile('token', content);
        const error = await failure(resolve({ tokenFile, ...url }));

        expect(error).toBeInstanceOf(UsageError);
        expect(error.message).toContain(tokenFile);
        expect(error.message).not.toContain('xy');
      });
    }

    it('rejects a directory', async () => {
      const error = await failure(resolve({ tokenFile: dir, ...url }));

      expect(error).toBeInstanceOf(UsageError);
      expect(error.message).toContain('not a regular file');
    });

    it('rejects /dev/stdin', async () => {
      const error = await failure(resolve({ tokenFile: '/dev/stdin', ...url }));

      expect(error).toBeInstanceOf(UsageError);
      expect(error.message).toContain('/dev/stdin');
    });

    it('rejects a FIFO without blocking', async () => {
      const fifo = join(dir, 'fifo');
      execFileSync('mkfifo', [fifo]);
      const error = await failure(resolve({ tokenFile: fifo, ...url }));

      expect(error).toBeInstanceOf(UsageError);
      expect(error.message).toContain('not a regular file');
    });

    it('accepts a symlink to a regular file', async () => {
      const target = await withFile('real', 'linked-token');
      const link = join(dir, 'link');
      await symlink(target, link);

      expect((await resolve({ tokenFile: link, ...url })).token).toBe('linked-token');
    });

    it('decodes invalid UTF-8 as replacement characters', async () => {
      const tokenFile = await withFile('token', new Uint8Array([0x61, 0xff, 0x62]));

      expect((await resolve({ tokenFile, ...url })).token).toBe('a�b');
    });
  });

  describe('mode warning', () => {
    const url = { url: 'https://x.example.com' };

    it('does not warn for an owner-only file', async () => {
      const tokenFile = await withFile('token', 'abc', 0o600);
      await resolve({ tokenFile, ...url });

      expect(stderr.text).toBe('');
    });

    for (const mode of [0o640, 0o604]) {
      it(`warns, naming the path, for mode ${mode.toString(8)} and still resolves`, async () => {
        const tokenFile = await withFile('token', 'abc', mode);
        const result = await resolve({ tokenFile, ...url });

        expect(result.token).toBe('abc');
        expect(stderr.text).toContain(tokenFile);
        expect(stderr.text).toMatch(/^pidgey: warning: /);
      });
    }

    it('does not warn on win32', async () => {
      const tokenFile = await withFile('token', 'abc', 0o644);
      await resolve({ tokenFile, ...url }, {}, 'win32');

      expect(stderr.text).toBe('');
    });
  });
});
