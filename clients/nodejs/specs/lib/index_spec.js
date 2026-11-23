import * as entry from '../../lib/index.js';
import { PidgeyClient } from '../../lib/PidgeyClient.js';
import { PidgeyError } from '../../lib/PidgeyError.js';

describe('index', () => {
  it('exports PidgeyClient', () => {
    expect(entry.PidgeyClient).toBe(PidgeyClient);
  });

  it('exports PidgeyError', () => {
    expect(entry.PidgeyError).toBe(PidgeyError);
  });
});
