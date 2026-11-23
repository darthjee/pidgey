import { readFileSync } from 'node:fs';
import { VERSION } from '../../lib/version.js';

describe('VERSION', () => {
  it('equals the version of package.json', () => {
    const pkg = JSON.parse(readFileSync(new URL('../../package.json', import.meta.url), 'utf8'));

    expect(VERSION).toBe(pkg.version);
  });
});
