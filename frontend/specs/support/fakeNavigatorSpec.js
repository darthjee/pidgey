import { fakeLocks, installFakeNavigator, uninstallFakeNavigator } from './fakeNavigator.js';

describe('fakeNavigator', () => {
  let realDescriptor;

  beforeEach(() => {
    realDescriptor = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
  });

  afterEach(() => {
    uninstallFakeNavigator();
    if (realDescriptor) {
      Object.defineProperty(globalThis, 'navigator', realDescriptor);
    } else {
      delete globalThis.navigator;
    }
  });

  describe('installFakeNavigator', () => {
    it('installs the fake as globalThis.navigator', () => {
      const fake = { locks: fakeLocks() };

      installFakeNavigator(fake);

      expect(globalThis.navigator).toBe(fake);
    });

    it('returns the installed fake', () => {
      const fake = {};

      expect(installFakeNavigator(fake)).toBe(fake);
    });

    it('keeps the true original when installed a second time', () => {
      const original = { original: true };
      Object.defineProperty(globalThis, 'navigator', { value: original, configurable: true, writable: true });

      installFakeNavigator({ first: true });
      installFakeNavigator({ second: true });
      uninstallFakeNavigator();

      expect(globalThis.navigator).toBe(original);
    });
  });

  describe('uninstallFakeNavigator', () => {
    it('deletes navigator when none existed before', () => {
      delete globalThis.navigator;
      installFakeNavigator({});

      uninstallFakeNavigator();

      expect('navigator' in globalThis).toBe(false);
    });

    it('restores the original property descriptor', () => {
      const before = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
      installFakeNavigator({});

      uninstallFakeNavigator();

      expect(Object.getOwnPropertyDescriptor(globalThis, 'navigator')).toEqual(before);
    });

    it('is a no-op when nothing was installed', () => {
      const before = Object.getOwnPropertyDescriptor(globalThis, 'navigator');

      uninstallFakeNavigator();

      expect(Object.getOwnPropertyDescriptor(globalThis, 'navigator')).toEqual(before);
    });
  });

  describe('fakeLocks', () => {
    it('runs the callback right away and resolves with its result', async () => {
      const locks = fakeLocks();

      const result = await locks.request('lock-name', () => Promise.resolve('done'));

      expect(result).toBe('done');
      expect(locks.request).toHaveBeenCalledWith('lock-name', jasmine.any(Function));
    });
  });
});
