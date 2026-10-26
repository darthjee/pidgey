let installed = false;
let previousDescriptor;

/**
 * Installs a fake as `globalThis.navigator`, remembering the original property.
 *
 * @description Depending on its version, Node exposes `navigator` as a getter-only global
 * (with or without `navigator.locks`), so the fake is installed with `defineProperty`. Pass
 * `{}` to run without the Web Locks API, or `{ locks }` to stub it. The original property is
 * recorded on the first install since the last uninstall. Pair with `uninstallFakeNavigator()`
 * in an `afterEach`.
 * @param {object} fake - The fake navigator.
 * @returns {object} The same fake, so callers can assert on it.
 */
export const installFakeNavigator = (fake) => {
  if (!installed) {
    installed = true;
    previousDescriptor = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
  }
  Object.defineProperty(globalThis, 'navigator', { value: fake, configurable: true, writable: true });
  return fake;
};

/**
 * Builds a fake `navigator.locks` whose `request` runs the callback right away.
 *
 * @returns {{request: jasmine.Spy}} The fake lock manager.
 */
export const fakeLocks = () => ({
  request: jasmine.createSpy('locks.request').and.callFake((_name, callback) => callback()),
});

/**
 * Restores `globalThis.navigator` to its state before the first `installFakeNavigator()` call.
 *
 * @description Restores the original property descriptor, or deletes `navigator` when there
 * was none. A no-op when nothing was installed, so it is safe in a top-level `afterEach`.
 * @returns {void}
 */
export const uninstallFakeNavigator = () => {
  if (!installed) {
    return;
  }
  if (previousDescriptor) {
    Object.defineProperty(globalThis, 'navigator', previousDescriptor);
  } else {
    delete globalThis.navigator;
  }
  installed = false;
  previousDescriptor = undefined;
};
