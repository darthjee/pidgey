import SmtpConnectionsClient from '../../../../../client/SmtpConnectionsClient.js';

/**
 * Confirmation text shown before deleting an inactive SMTP connection.
 *
 * @type {string}
 */
export const DELETE_CONFIRM_MESSAGE = 'Delete this SMTP connection? This cannot be undone.';

/**
 * Confirmation text shown before deleting the active SMTP connection: sending stops until
 * another connection is activated.
 *
 * @type {string}
 */
export const DELETE_ACTIVE_CONFIRM_MESSAGE = 'Delete the active SMTP connection? Sending stops '
  + '(POST /v1/messages answers 409 NO_SMTP_CONNECTION) until another connection is activated.';

const defaultConfirm = (message) => window.confirm(message);

/**
 * Controller for the SMTP connections list page: loads the organization's connections,
 * activates one and deletes one. Every method that reaches {@link SmtpConnectionsClient}
 * treats an `undefined` result the same way: return without touching any further state —
 * `ApiClient` already opened the login modal itself on a session-expired `401`.
 */
export default class SmtpConnectionsController {
  /**
   * Create an SMTP connections controller.
   *
   * @param {object} options - Controller dependencies.
   * @param {Function} options.setConnections - React state setter for the listed connections.
   * @param {Function} options.setLoadState - React state setter for the load state,
   *   `{ loading: boolean, loadError: (string|null) }`.
   * @param {Function} options.setListError - React state setter for the error of the last
   *   row action (activate / delete).
   * @param {typeof SmtpConnectionsClient} [options.client] - SMTP connections HTTP client
   *   override, for testability.
   * @param {Function} [options.confirm] - Confirmation dialog override (receives the message
   *   and returns a boolean), for testability; defaults to `window.confirm`.
   */
  constructor({
    setConnections, setLoadState, setListError,
    client = SmtpConnectionsClient, confirm = defaultConfirm,
  }) {
    this.setConnections = setConnections;
    this.setLoadState = setLoadState;
    this.setListError = setListError;
    this.client = client;
    this.confirm = confirm;
  }

  /**
   * Load the organization's connections, or store the load error on failure. When the session
   * ended (`undefined` result), the screen is left idle, without an error.
   *
   * @returns {Promise<void>} Resolves once the load finishes.
   */
  async load() {
    try {
      const result = await this.client.list();

      if (result) {
        this.setConnections(result.smtpConnections);
      }

      this.setLoadState({ loading: false, loadError: null });
    } catch (error) {
      this.setLoadState({ loading: false, loadError: error.message });
    }
  }

  /**
   * Activate a connection; on success it becomes the only active row (replaced by the
   * returned item), on failure the error is shown above the list.
   *
   * @param {number} id - The connection id.
   * @returns {Promise<void>} Resolves once the request finishes.
   */
  async handleActivate(id) {
    this.setListError(null);

    try {
      const result = await this.client.activate(id);

      if (!result) {
        return;
      }

      const activated = result.smtpConnection;

      this.setConnections((current) => current.map(
        (connection) => (connection.id === activated.id ? activated : { ...connection, active: false }),
      ));
    } catch (error) {
      this.setListError(error.message);
    }
  }

  /**
   * Ask for confirmation (with a warning when the connection is the active one), then delete
   * the connection and remove its row; on failure the error is shown above the list.
   * Cancelling sends nothing.
   *
   * @param {{id: number, active: boolean}} connection - The row's connection.
   * @returns {Promise<void>} Resolves once the request finishes (or immediately when cancelled).
   */
  async handleDelete(connection) {
    const message = connection.active ? DELETE_ACTIVE_CONFIRM_MESSAGE : DELETE_CONFIRM_MESSAGE;

    if (!this.confirm(message)) {
      return;
    }

    this.setListError(null);

    try {
      const result = await this.client.remove(connection.id);

      if (!result) {
        return;
      }

      this.setConnections((current) => current.filter((item) => item.id !== connection.id));
    } catch (error) {
      this.setListError(error.message);
    }
  }
}
