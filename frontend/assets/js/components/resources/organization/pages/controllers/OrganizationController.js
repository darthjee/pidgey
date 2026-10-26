import OrganizationClient from '../../../../../client/OrganizationClient.js';
import { validateOrganizationName } from '../../../../../utils/validation/fieldValidators.js';

const VALIDATION_FAILED = 'VALIDATION_FAILED';

/**
 * Controller for the Organization page: loads the caller's own organization and renames it.
 * Every method that reaches {@link OrganizationClient} treats an `undefined` result the same
 * way: return without touching any further state — {@link module:client/ApiClient} already
 * opened the login modal itself on a session-expired `401`.
 */
export default class OrganizationController {
  /**
   * Create an Organization controller.
   *
   * @param {Function} setFields - React state setter for the form fields, `{ name }`.
   * @param {Function} setFieldErrors - React state setter for per-field validation errors.
   * @param {Function} setLoadState - React state setter for the load state,
   *   `{ loading: boolean, loadError: (string|null) }`.
   * @param {Function} setSubmitError - React state setter for the submit-time error message.
   * @param {Function} setSuccess - React state setter for the success confirmation flag.
   * @param {typeof OrganizationClient} [client] - Organization HTTP client override, for
   *   testability.
   */
  constructor(setFields, setFieldErrors, setLoadState, setSubmitError, setSuccess, client = OrganizationClient) {
    this.setFields = setFields;
    this.setFieldErrors = setFieldErrors;
    this.setLoadState = setLoadState;
    this.setSubmitError = setSubmitError;
    this.setSuccess = setSuccess;
    this.client = client;
  }

  /**
   * Load the caller's organization into the form, or store the load error on failure. When the
   * session ended (`undefined` result), the screen is left idle, without an error.
   *
   * @returns {Promise<void>} Resolves once the load finishes.
   */
  async load() {
    try {
      const result = await this.client.get();

      if (result) {
        this.setFields({ name: result.organization.name });
      }

      this.setLoadState({ loading: false, loadError: null });
    } catch (error) {
      this.setLoadState({ loading: false, loadError: error.message });
    }
  }

  /**
   * Validate the form: the trimmed name must be present and at most 255 characters long.
   *
   * @param {{name: string}} fields - Current form field values.
   * @returns {object} A map of field name to error message, empty when the form is valid.
   */
  validate({ name }) {
    return validateOrganizationName(name, 'name');
  }

  /**
   * Submit the rename: clear the previous outcome, validate, and — when valid — send the
   * trimmed name. On success the returned name is put back into the form and the success
   * flag is set; a `VALIDATION_FAILED` error goes on the name field, any other error becomes
   * the submit error.
   *
   * @param {{name: string}} fields - Current form field values.
   * @returns {Promise<void>} Resolves once submission handling finishes.
   */
  async handleSubmit(fields) {
    const errors = this.validate(fields);

    this.setSuccess(false);
    this.setSubmitError(null);
    this.setFieldErrors(errors);

    if (Object.keys(errors).length > 0) {
      return;
    }

    try {
      const result = await this.client.update({ name: fields.name.trim() });

      if (!result) {
        return;
      }

      this.setFields({ name: result.organization.name });
      this.setSuccess(true);
    } catch (error) {
      this.#handleSubmitError(error);
    }
  }

  #handleSubmitError(error) {
    if (error.code === VALIDATION_FAILED) {
      this.setFieldErrors({ name: error.details?.join('; ') || error.message });
      return;
    }

    this.setSubmitError(error.message);
  }
}
