import * as entry from '../../lib/index.js';
import { PidgeyClient } from '../../lib/PidgeyClient.js';
import { PidgeyError } from '../../lib/PidgeyError.js';
import { VERSION } from '../../lib/version.js';

describe('index', () => {
  it('exports PidgeyClient', () => {
    expect(entry.PidgeyClient).toBe(PidgeyClient);
  });

  it('exports PidgeyError', () => {
    expect(entry.PidgeyError).toBe(PidgeyError);
  });

  it('exports VERSION', () => {
    expect(entry.VERSION).toBe(VERSION);
  });

  it('exports nothing else', () => {
    expect(Object.keys(entry).sort()).toEqual(['PidgeyClient', 'PidgeyError', 'VERSION']);
  });
});
