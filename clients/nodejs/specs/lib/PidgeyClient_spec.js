import { inspect } from 'node:util';
import { PidgeyClient } from '../../lib/PidgeyClient.js';
import { PidgeyError } from '../../lib/PidgeyError.js';

const TOKEN = 'pdg_secret_token';

describe('PidgeyClient', () => {
  describe('constructor', () => {
    it('builds a client from valid options', () => {
      expect(new PidgeyClient({ token: TOKEN, baseUrl: 'https://pidgey.example.com' }))
        .toBeInstanceOf(PidgeyClient);
    });

    it('throws a CLIENT_CONFIGURATION PidgeyError synchronously', () => {
      let caught;
      try {
        new PidgeyClient();
      } catch (error) {
        caught = error;
      }

      expect(caught).toBeInstanceOf(PidgeyError);
      expect(caught.code).toBe('CLIENT_CONFIGURATION');
    });
  });

  describe('inspectable state', () => {
    const client = new PidgeyClient({ token: TOKEN, baseUrl: 'https://pidgey.example.com' });

    it('exposes no own property', () => {
      expect(Object.getOwnPropertyNames(client)).toEqual([]);
    });

    it('does not leak the token through inspect or JSON', () => {
      expect(inspect(client, { showHidden: true })).not.toContain(TOKEN);
      expect(JSON.stringify(client)).not.toContain(TOKEN);
    });
  });
});
