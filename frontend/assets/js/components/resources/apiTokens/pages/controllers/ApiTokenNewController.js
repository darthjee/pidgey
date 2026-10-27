import ApiTokensClient from '../../../../../client/ApiTokensClient.js';
import ApiTokenFormRules from './ApiTokenFormRules.js';

const VALIDATION_FAILED = 'VALIDATION_FAILED';

/**
 * Copy state shown by the one-time token panel: nothing copied yet.
 *
 * @type {string}
 */
export const COPY_IDLE = 'idle';

/**
 * Copy state shown by the one-time token panel: the token was copied to the clipboard.
 *
 * @type {string}
 */
export const COPY_DONE = 'copied';

/**
 * Copy state shown by the one-time token panel: the clipboard is missing or refused the copy.
 *
 * @type {string}
 */
export const COPY_FAILED = 'failed';

/**
 * Controller for the API token create page: toggles scopes, validates and submits the form,
 * and copies the created token. The plain token is only handed to `setCreatedToken` (the
 * page's component state); it is never stored, persisted nor logged here. A submit resolving
 * to `undefined` (session ended) returns without touching any further state — `ApiClient`
 * already opened the login modal itself.
 */
export default class ApiTokenNewController {
  /**
   * Create an API token create-page controller.
   *
   * @param {object} options - Controller dependencies.
   * @param {Function} options.setScopes - React state setter for the selected scopes.
   * @param {Function} options.setFieldErrors - React state setter for per-field errors.
   * @param {Function} options.setSubmitError - React state setter for the submit error.
   * @param {Function} options.setSubmitting - React state setter for the in-flight flag.
   * @param {Function} options.setCreatedToken - React state setter for the plain token shown
   *   once after creation.
   * @param {Function} options.setCopyState - React state setter for the copy state
   *   ({@link COPY_IDLE}, {@link COPY_DONE} or {@link COPY_FAILED}).
   * @param {typeof ApiTokensClient} [options.client] - HTTP client override, for testability.
   * @param {{writeText: Function}} [options.clipboard] - Clipboard override, for testability;
   *   defaults to `navigator.clipboard`, looked up when copying.
   */
  constructor({
    setScopes, setFieldErrors, setSubmitError, setSubmitting, setCreatedToken, setCopyState,
    client = ApiTokensClient, clipboard,
  }) {
    this.setScopes = setScopes;
    this.setFieldErrors = setFieldErrors;
    this.setSubmitError = setSubmitError;
    this.setSubmitting = setSubmitting;
    this.setCreatedToken = setCreatedToken;
    this.setCopyState = setCopyState;
    this.client = client;
    this.clipboard = clipboard;
  }

  /**
   * Select or unselect a scope.
   *
   * @param {string} scope - The scope name.
   * @returns {void}
   */
  toggleScope(scope) {
    this.setScopes((current) => (
      current.includes(scope) ? current.filter((item) => item !== scope) : [...current, scope]
    ));
  }

  /**
   * Validate and submit the form. Invalid input sets the field errors and sends nothing;
   * otherwise the trimmed name and the scopes are sent and, on success, the plain token is
   * handed to the one-time panel.
   *
   * @param {{name: string, scopes: string[]}} fields - Form field values.
   * @returns {Promise<void>} Resolves once submission handling finishes.
   */
  async handleSubmit(fields) {
    const errors = ApiTokenFormRules.validate(fields);

    this.setSubmitError(null);
    this.setFieldErrors(errors);

    if (Object.keys(errors).length > 0) {
      return;
    }

    this.setSubmitting(true);

    try {
      const result = await this.client.create({ name: fields.name.trim(), scopes: fields.scopes });

      if (result) {
        this.setCopyState(COPY_IDLE);
        this.setCreatedToken(result.apiToken.token);
      }
    } catch (error) {
      this.#handleSubmitError(error);
    } finally {
      this.setSubmitting(false);
    }
  }

  /**
   * Copy the token to the clipboard. A missing clipboard (non-secure context) or a rejected
   * write sets {@link COPY_FAILED}, so the panel shows the manual-copy fallback.
   *
   * @param {string} token - The plain token.
   * @returns {Promise<void>} Resolves once the copy attempt finishes.
   */
  async handleCopy(token) {
    const clipboard = this.clipboard ?? globalThis.navigator?.clipboard;

    try {
      await clipboard.writeText(token);
      this.setCopyState(COPY_DONE);
    } catch {
      this.setCopyState(COPY_FAILED);
    }
  }

  #handleSubmitError(error) {
    if (error.code !== VALIDATION_FAILED || !error.details?.length) {
      this.setSubmitError(error.message);
      return;
    }

    const { fieldErrors, unmatched } = ApiTokenFormRules.mapValidationDetails(error.details);

    this.setFieldErrors(fieldErrors);

    if (unmatched.length > 0) {
      this.setSubmitError(unmatched.join('; '));
    }
  }
}
