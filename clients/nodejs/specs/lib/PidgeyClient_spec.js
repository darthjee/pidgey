import { PidgeyClient } from '../../lib/PidgeyClient.js';

describe('PidgeyClient', () => {
  it('stores a copy of the options', () => {
    const options = { baseUrl: 'http://127.0.0.1' };
    const client = new PidgeyClient(options);

    expect(client.options).toEqual({ baseUrl: 'http://127.0.0.1' });
    expect(client.options).not.toBe(options);
  });

  it('freezes the stored options', () => {
    expect(Object.isFrozen(new PidgeyClient({ a: 1 }).options)).toBe(true);
  });

  it('defaults the options to an empty object', () => {
    expect(new PidgeyClient().options).toEqual({});
  });
});
