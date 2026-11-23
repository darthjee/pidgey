import { isLoopbackHost, resolveConfig } from '../../lib/config.js';
import { PidgeyError } from '../../lib/PidgeyError.js';

const TOKEN = 'pdg_secret_token';
const valid = (extra = {}) => ({ token: TOKEN, baseUrl: 'https://pidgey.example.com', ...extra });

const expectConfigError = (options, pattern) => {
  let caught;
  try {
    resolveConfig(options);
  } catch (error) {
    caught = error;
  }
  expect(caught instanceof PidgeyError).toBe(true);
  expect(caught.code).toBe('CLIENT_CONFIGURATION');
  expect(caught.possiblySent).toBe(false);
  if (pattern) {
    expect(caught.message).toMatch(pattern);
  }
  return caught;
};

describe('resolveConfig', () => {
  describe('defaults', () => {
    it('applies every default', () => {
      expect(resolveConfig(valid())).toEqual({
        token: TOKEN,
        endpoint: 'https://pidgey.example.com/v1/messages',
        allowInsecureHttp: false,
        maxRetries: 2,
        retryDelayMs: 500,
        retryAmbiguous: false,
        timeoutMs: 30000,
      });
    });

    it('returns a frozen object', () => {
      expect(Object.isFrozen(resolveConfig(valid()))).toBe(true);
    });

    it('treats undefined options as the defaults', () => {
      const config = resolveConfig(valid({
        allowInsecureHttp: undefined, maxRetries: undefined, retryDelayMs: undefined,
        retryAmbiguous: undefined, timeoutMs: undefined,
      }));

      expect(config.maxRetries).toBe(2);
      expect(config.timeoutMs).toBe(30000);
    });

    it('keeps the given values', () => {
      const config = resolveConfig(valid({
        allowInsecureHttp: true, maxRetries: 0, retryDelayMs: 0, retryAmbiguous: true, timeoutMs: 1,
      }));

      expect(config.allowInsecureHttp).toBe(true);
      expect(config.maxRetries).toBe(0);
      expect(config.retryDelayMs).toBe(0);
      expect(config.retryAmbiguous).toBe(true);
      expect(config.timeoutMs).toBe(1);
    });
  });

  describe('options object', () => {
    it('rejects no options', () => {
      expectConfigError(undefined, /token/);
    });

    it('rejects null options', () => {
      expectConfigError(null, /token/);
    });
  });

  describe('token', () => {
    for (const token of [undefined, null, '', '   ', '\t\n', 42, {}]) {
      it(`rejects ${JSON.stringify(token)}`, () => {
        expectConfigError(valid({ token }), /token/);
      });
    }

    it('keeps a token as given', () => {
      expect(resolveConfig(valid({ token: ' abc ' })).token).toBe(' abc ');
    });
  });

  describe('baseUrl', () => {
    for (const baseUrl of [undefined, null, 42, '', 'pidgey.example.com', '/relative', 'http://']) {
      it(`rejects ${JSON.stringify(baseUrl)}`, () => {
        expectConfigError(valid({ baseUrl }), /baseUrl/);
      });
    }

    for (const baseUrl of ['ftp://example.com', 'file:///tmp/x', 'mailto:a@b.c', 'ws://example.com']) {
      it(`rejects the scheme of ${baseUrl}`, () => {
        expectConfigError(valid({ baseUrl }), /scheme/);
      });
    }

    const endpoints = {
      'https://pidgey.example.com': 'https://pidgey.example.com/v1/messages',
      'https://pidgey.example.com/': 'https://pidgey.example.com/v1/messages',
      'https://example.com/pidgey': 'https://example.com/pidgey/v1/messages',
      'https://example.com/pidgey/': 'https://example.com/pidgey/v1/messages',
      'https://example.com/a//': 'https://example.com/a/v1/messages',
      'https://example.com:8443/x?y=1#z': 'https://example.com:8443/x/v1/messages',
      'https://user:pass@example.com': 'https://example.com/v1/messages',
    };
    for (const [baseUrl, endpoint] of Object.entries(endpoints)) {
      it(`normalizes ${baseUrl}`, () => {
        expect(resolveConfig(valid({ baseUrl })).endpoint).toBe(endpoint);
      });
    }
  });

  describe('cleartext rule', () => {
    for (const host of ['localhost', 'LOCALHOST', '127.0.0.1', '127.1', '[::1]', '127.255.0.3']) {
      it(`accepts http://${host} without opt-in`, () => {
        const config = resolveConfig(valid({ baseUrl: `http://${host}:8080` }));

        expect(config.endpoint).toMatch(/^http:\/\/.+:8080\/v1\/messages$/);
      });
    }

    for (const host of ['localhost.', 'foo.localhost', '[::ffff:127.0.0.1]', '0.0.0.0', 'example.com']) {
      it(`rejects http://${host} without opt-in`, () => {
        const error = expectConfigError(valid({ baseUrl: `http://${host}` }), /clear text/);

        expect(error.message).toMatch(/allowInsecureHttp: true/);
      });

      it(`accepts http://${host} with allowInsecureHttp: true`, () => {
        const config = resolveConfig(valid({ baseUrl: `http://${host}`, allowInsecureHttp: true }));

        expect(config.endpoint).toMatch(/^http:\/\//);
      });
    }

    it('rejects http://example.com with allowInsecureHttp: false', () => {
      expectConfigError(valid({ baseUrl: 'http://example.com', allowInsecureHttp: false }), /clear text/);
    });

    it('ignores the option on https://', () => {
      expect(resolveConfig(valid({ allowInsecureHttp: true })).endpoint)
        .toBe('https://pidgey.example.com/v1/messages');
    });

    it('never echoes the token or the userinfo of the URL', () => {
      const error = expectConfigError(valid({ baseUrl: 'http://user:pass@example.com' }), /clear text/);

      expect(error.message).not.toContain(TOKEN);
      expect(error.message).not.toContain('user:pass');
      expect(error.message).not.toContain('user');
      expect(error.message).not.toContain('example.com');
    });
  });

  describe('numeric options', () => {
    const bad = [-1, 1.5, '2', NaN, Infinity, null, true];
    for (const name of ['maxRetries', 'retryDelayMs']) {
      for (const value of bad) {
        it(`rejects ${name} ${String(value)}`, () => {
          expectConfigError(valid({ [name]: value }), new RegExp(name));
        });
      }
    }

    for (const value of [0, -5, 1.5, '30', NaN, Infinity, null]) {
      it(`rejects timeoutMs ${String(value)}`, () => {
        expectConfigError(valid({ timeoutMs: value }), /timeoutMs/);
      });
    }
  });

  describe('boolean options', () => {
    for (const name of ['allowInsecureHttp', 'retryAmbiguous']) {
      for (const value of [1, 'true', null, {}]) {
        it(`rejects ${name} ${JSON.stringify(value)}`, () => {
          expectConfigError(valid({ [name]: value }), new RegExp(name));
        });
      }
    }
  });

  it('never includes the token in a message', () => {
    const error = expectConfigError({ token: TOKEN, baseUrl: 'nope', maxRetries: -1 });

    expect(error.message).not.toContain(TOKEN);
  });
});

describe('isLoopbackHost', () => {
  it('accepts the loopback hosts', () => {
    for (const host of ['localhost', '127.0.0.1', '127.9.9.9', '[::1]']) {
      expect(isLoopbackHost(host)).toBe(true);
    }
  });

  it('rejects everything else', () => {
    for (const host of ['localhost.', 'a.localhost', '[::ffff:7f00:1]', '0.0.0.0', 'example.com', '128.0.0.1', '']) {
      expect(isLoopbackHost(host)).toBe(false);
    }
  });
});
