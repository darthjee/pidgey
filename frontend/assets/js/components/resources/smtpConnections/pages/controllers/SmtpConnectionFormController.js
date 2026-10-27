import SmtpConnectionsClient from '../../../../../client/SmtpConnectionsClient.js';
import { redirectTo } from '../../../../../utils/routing/redirects.js';
import SmtpConnectionFormRules from './SmtpConnectionFormRules.js';

const VALIDATION_FAILED = 'VALIDATION_FAILED';
const LIST_PATH = '/smtp-connections';

/**
 * Turn an SMTP connection item into form field values; the password input is always empty
 * (the API never returns the password).
 *
 * @param {object} item - The SMTP connection item.
 * @returns {object} The form field values.
 */
function fieldsFrom(item) {
  return {
    name: item.name,
    host: item.host,
    port: String(item.port),
    encryption: item.encryption,
    username: item.username ?? '',
    password: '',
    fromAddress: item.fromAddress,
  };
}

/**
 * Controller for the SMTP connection create / edit form: loads the connection on edit,
 * validates, submits and removes the credentials. Every method that reaches
 * {@link SmtpConnectionsClient} treats an `undefined` result the same way: return without
 * touching any further state — `ApiClient` already opened the login modal itself.
 */
export default class SmtpConnectionFormController {
  /**
   * Create an SMTP connection form controller.
   *
   * @param {object} options - Controller dependencies.
   * @param {Function} options.setFields - React state setter for the form fields.
   * @param {Function} options.setStored - React state setter for the stored credentials,
   *   `{ username: (string|null), hasPassword: boolean }`.
   * @param {Function} options.setLoadState - React state setter for the load state,
   *   `{ loading: boolean, loadError: (string|null), notFound: boolean }`.
   * @param {Function} options.setFieldErrors - React state setter for per-field errors.
   * @param {Function} options.setSubmitError - React state setter for the submit error.
   * @param {Function} options.setSuccess - React state setter for the success flag.
   * @param {typeof SmtpConnectionsClient} [options.client] - HTTP client override, for
   *   testability.
   * @param {Function} [options.navigate] - Navigation override (receives the route path), for
   *   testability; defaults to {@link redirectTo}.
   */
  constructor({
    setFields, setStored, setLoadState, setFieldErrors, setSubmitError, setSuccess,
    client = SmtpConnectionsClient, navigate = redirectTo,
  }) {
    this.setFields = setFields;
    this.setStored = setStored;
    this.setLoadState = setLoadState;
    this.setFieldErrors = setFieldErrors;
    this.setSubmitError = setSubmitError;
    this.setSuccess = setSuccess;
    this.client = client;
    this.navigate = navigate;
  }

  /**
   * Load the connection being edited into the form. A `404` marks it as not found; any other
   * failure stores the load error; an ended session leaves the screen idle.
   *
   * @param {(number|string)} id - The connection id, from the route.
   * @returns {Promise<void>} Resolves once the load finishes.
   */
  async load(id) {
    try {
      const result = await this.client.get(id);

      if (result) {
        this.#applyItem(result.smtpConnection);
      }

      this.setLoadState({ loading: false, loadError: null, notFound: false });
    } catch (error) {
      const notFound = error.status === 404;

      this.setLoadState({ loading: false, loadError: notFound ? null : error.message, notFound });
    }
  }

  /**
   * Validate and submit the form: create when there is no `id` (then back to the list),
   * update otherwise (then refill the form from the returned item, clearing the password
   * input, and confirm).
   *
   * @param {(number|string|undefined)} id - The connection id on edit; `undefined` on create.
   * @param {object} fields - Form field values.
   * @param {({username: (string|null), hasPassword: boolean}|null)} stored - The stored
   *   credentials on edit; `null` on create.
   * @returns {Promise<void>} Resolves once submission handling finishes.
   */
  async handleSubmit(id, fields, stored) {
    const errors = SmtpConnectionFormRules.validate(fields, stored);

    this.#resetOutcome(errors);

    if (Object.keys(errors).length > 0) {
      return;
    }

    if (id === undefined) {
      await this.#create(fields);
    } else {
      await this.#update(id, SmtpConnectionFormRules.buildUpdatePayload(fields, stored));
    }
  }

  /**
   * Remove the stored credentials: sends `username: ""` and `password: ""`.
   *
   * @param {(number|string)} id - The connection id.
   * @returns {Promise<void>} Resolves once the request finishes.
   */
  async handleRemoveCredentials(id) {
    this.#resetOutcome({});
    await this.#update(id, { username: '', password: '' });
  }

  async #create(fields) {
    try {
      const result = await this.client.create(SmtpConnectionFormRules.buildCreatePayload(fields));

      if (result) {
        this.navigate(LIST_PATH);
      }
    } catch (error) {
      this.#handleSubmitError(error);
    }
  }

  async #update(id, payload) {
    try {
      const result = await this.client.update(id, payload);

      if (!result) {
        return;
      }

      this.#applyItem(result.smtpConnection);
      this.setSuccess(true);
    } catch (error) {
      this.#handleSubmitError(error);
    }
  }

  #applyItem(item) {
    this.setFields(fieldsFrom(item));
    this.setStored({ username: item.username, hasPassword: item.hasPassword });
  }

  #resetOutcome(errors) {
    this.setSuccess(false);
    this.setSubmitError(null);
    this.setFieldErrors(errors);
  }

  #handleSubmitError(error) {
    if (error.code !== VALIDATION_FAILED || !error.details?.length) {
      this.setSubmitError(error.message);
      return;
    }

    const { fieldErrors, unmatched } = SmtpConnectionFormRules.mapValidationDetails(error.details);

    this.setFieldErrors(fieldErrors);

    if (unmatched.length > 0) {
      this.setSubmitError(unmatched.join('; '));
    }
  }
}
