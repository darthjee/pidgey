import SmtpConnectionsClient from '../../../../../client/SmtpConnectionsClient.js';

const VALIDATION_FAILED = 'VALIDATION_FAILED';
const SMTP_CODE_PREFIX = 'SMTP_';

/**
 * State of the closed test-email modal; opening it sets `connection`.
 *
 * @type {{connection: null, to: string, sending: boolean, result: null, error: null,
 *   smtp: null, fieldError: null}}
 */
export const CLOSED_TEST_STATE = {
  connection: null, to: '', sending: false, result: null, error: null, smtp: null, fieldError: null,
};

const CLEARED_OUTCOME = { result: null, error: null, smtp: null, fieldError: null };

/**
 * Map a failed test email to the modal state changes: a `VALIDATION_FAILED` goes on the
 * recipient field; an `SMTP_*` failure shows its message plus the SMTP server reply, when
 * present; any other error shows its message.
 *
 * @param {Error} error - The thrown error (usually an `ApiError`).
 * @returns {object} The modal state changes.
 */
function failureChanges(error) {
  if (error.code === VALIDATION_FAILED) {
    return { fieldError: error.details?.join('; ') || error.message };
  }

  if (error.code?.startsWith(SMTP_CODE_PREFIX)) {
    return { error: error.message, smtp: error.smtp ?? null };
  }

  return { error: error.message };
}

/**
 * Build the state updater applying a recipient change to the test-email modal state.
 * Extracted as a plain function so it can be exercised directly in tests without a React
 * renderer.
 *
 * @param {string} to - The recipient input's new value.
 * @returns {Function} State updater returning the modal state with `to` set.
 */
export function buildRecipientUpdate(to) {
  return (current) => ({ ...current, to });
}

/**
 * Controller for the test-email modal of the SMTP connections list: opens / closes it and
 * sends the test email. The request is synchronous and can be slow, so `sending` stays `true`
 * until it settles.
 */
export default class SmtpConnectionTestController {
  /**
   * Create a test-email controller.
   *
   * @param {Function} setTestState - React state setter for the modal state (see
   *   {@link CLOSED_TEST_STATE}).
   * @param {typeof SmtpConnectionsClient} [client] - SMTP connections HTTP client override,
   *   for testability.
   */
  constructor(setTestState, client = SmtpConnectionsClient) {
    this.setTestState = setTestState;
    this.client = client;
  }

  /**
   * Open the modal for a connection, with an empty recipient and no outcome.
   *
   * @param {{id: number, name: string}} connection - The row's connection.
   * @returns {void}
   */
  open(connection) {
    this.setTestState({ ...CLOSED_TEST_STATE, connection });
  }

  /**
   * Close the modal and reset its state.
   *
   * @returns {void}
   */
  close() {
    this.setTestState(CLOSED_TEST_STATE);
  }

  /**
   * Send the test email: the trimmed recipient, or none when blank (the API then uses the
   * caller's own email). On `201` the SMTP reply is stored as `result`; failures are mapped by
   * {@link failureChanges}. When the session ended (`undefined` result), only `sending` is
   * reset.
   *
   * @param {{connection: {id: number}, to: string}} testState - The modal state: the
   *   connection under test and the recipient input value.
   * @returns {Promise<void>} Resolves once the request finishes.
   */
  async submit({ connection, to }) {
    this.#update({ ...CLEARED_OUTCOME, sending: true });

    try {
      const result = await this.client.test(connection.id, { to: to.trim() || undefined });

      this.#update({ sending: false, result: result ? result.smtp : null });
    } catch (error) {
      this.#update({ sending: false, ...failureChanges(error) });
    }
  }

  #update(changes) {
    this.setTestState((current) => ({ ...current, ...changes }));
  }
}
