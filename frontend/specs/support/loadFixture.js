import { readFileSync } from 'node:fs';
import ApiError from '../../assets/js/client/ApiError.js';

const FIXTURES_URL = new URL('./fixtures/', import.meta.url);

/**
 * Load a JSON fixture from `specs/support/fixtures/`.
 *
 * @description The file is read and parsed on every call, so each call returns a fresh object
 * that a spec can mutate freely. The path is resolved from this module's URL, so it works
 * whatever the current working directory is.
 * @param {string} name - The fixture name, relative to the fixtures folder and without the
 *   `.json` extension (e.g. `smtpConnections/list`).
 * @returns {object} The parsed fixture.
 */
export default function loadFixture(name) {
  return JSON.parse(readFileSync(new URL(`${name}.json`, FIXTURES_URL), 'utf8'));
}

/**
 * Build an `ApiError` from an error-envelope fixture.
 *
 * @description Uses the fixture's `statusCode` and its `error.message`, `error.code`,
 * `error.details` and `error.smtp`, the same way `ApiClient` builds it from a response.
 * @param {string} name - The fixture name (e.g. `smtpConnections/notFound`).
 * @returns {ApiError} The error the client would raise for that response.
 */
export function loadApiError(name) {
  const body = loadFixture(name);

  return new ApiError(body.statusCode, body.error.message, body.error.code, body.error.details, body.error.smtp);
}
