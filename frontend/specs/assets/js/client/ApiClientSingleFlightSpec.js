import ApiClient from '../../../../assets/js/client/ApiClient.js';
import AuthSession from '../../../../assets/js/client/AuthSession.js';
import LoginModalEvents from '../../../../assets/js/client/LoginModalEvents.js';
import { installFakeWindow, uninstallFakeWindow } from '../../../support/fakeWindow.js';
import { expectSessionExpired, fetchByPath } from '../../../support/fetchSequence.js';

const UNAUTHORIZED = { ok: false, status: 401, json: { error: { code: 'UNAUTHORIZED', message: 'Unauthorized' } } };
const REFRESHED = { ok: true, status: 200, json: { user: { id: 1 }, refreshToken: 'new-refresh-token' } };
const REFRESH_REJECTED = { ok: false, status: 401, json: { error: { code: 'UNAUTHORIZED', message: 'invalid refresh token' } } };

function ok(json) {
  return { ok: true, status: 200, json };
}

function deferred() {
  let resolve;
  const promise = new Promise((res) => {
    resolve = res;
  });

  return { promise, resolve };
}

function nextMacrotask() {
  return new Promise((resolve) => {
    setTimeout(resolve, 0);
  });
}

function refreshCalls() {
  return globalThis.fetch.calls.allArgs().filter(([path]) => path === '/auth/refresh.json');
}

describe('ApiClient single-flight refresh', () => {
  let originalFetch;

  beforeEach(() => {
    originalFetch = globalThis.fetch;
    installFakeWindow({ location: { hash: '' } });
    spyOn(LoginModalEvents, 'open');
    spyOn(AuthSession, 'get').and.returnValue('old-refresh-token');
    spyOn(AuthSession, 'set');
    spyOn(AuthSession, 'clear');
  });

  afterEach(() => {
    globalThis.fetch = originalFetch;
    uninstallFakeWindow();
  });

  async function runConcurrently(refreshResponse) {
    const refresh = deferred();

    globalThis.fetch = fetchByPath({
      '/a.json': [UNAUTHORIZED, ok({ name: 'a' })],
      '/b.json': [UNAUTHORIZED, ok({ name: 'b' })],
      '/auth/refresh.json': [refresh.promise],
    });

    const requests = Promise.all([ApiClient.postJson('/a.json', {}), ApiClient.postJson('/b.json', {})]);

    await nextMacrotask();
    refresh.resolve(refreshResponse);

    return requests;
  }

  it('makes a single refresh call for concurrent 401s and retries every request', async () => {
    const results = await runConcurrently(REFRESHED);

    expect(results).toEqual([{ name: 'a' }, { name: 'b' }]);
    expect(refreshCalls().length).toBe(1);
    expect(AuthSession.set).toHaveBeenCalledOnceWith('new-refresh-token');
    expect(AuthSession.clear).not.toHaveBeenCalled();
  });

  it('ends the session once when the shared refresh fails', async () => {
    const results = await runConcurrently(REFRESH_REJECTED);

    expect(results).toEqual([undefined, undefined]);
    expect(refreshCalls().length).toBe(1);
    expect(AuthSession.clear).toHaveBeenCalledTimes(1);
    expect(LoginModalEvents.open).toHaveBeenCalledTimes(1);
    expectSessionExpired();
  });

  it('starts a new refresh for a 401 that happens after the previous refresh settled', async () => {
    globalThis.fetch = fetchByPath({
      '/a.json': [UNAUTHORIZED, ok({ name: 'a' })],
      '/b.json': [UNAUTHORIZED, ok({ name: 'b' })],
      '/auth/refresh.json': [REFRESHED, REFRESHED],
    });

    expect(await ApiClient.postJson('/a.json', {})).toEqual({ name: 'a' });
    expect(await ApiClient.postJson('/b.json', {})).toEqual({ name: 'b' });
    expect(refreshCalls().length).toBe(2);
  });

  it('ends the session when the refresh request itself fails', async () => {
    globalThis.fetch = jasmine.createSpy('fetch').and.callFake((path) => (
      path === '/auth/refresh.json'
        ? Promise.reject(new TypeError('Failed to fetch'))
        : fetchByPath({ [path]: [UNAUTHORIZED] })(path)
    ));

    const data = await ApiClient.postJson('/a.json', {});

    expect(data).toBeUndefined();
    expect(AuthSession.clear).toHaveBeenCalledTimes(1);
    expectSessionExpired();
  });
});
