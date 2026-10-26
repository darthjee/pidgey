import {
  validateEmail, validateMaxLength, validatePort,
} from '../../../../../utils/validation/fieldValidators.js';

/** Maximum length of the SMTP connection string fields; mirrors the backend's limit. */
export const MAX_FIELD_LENGTH = 255;

/** The encryption modes the API accepts. */
export const ENCRYPTIONS = ['none', 'tls', 'ssl'];

/** The API's credential pairing message; shown on the password field. */
export const PAIRING_MESSAGE = 'username and password must be both set or both empty';

const REQUIRED_TEXT_FIELDS = new Map([
  ['name', 'Name'],
  ['host', 'Host'],
  ['fromAddress', 'From address'],
]);

const FIELD_NAMES = new Set(['name', 'host', 'port', 'encryption', 'username', 'password', 'fromAddress']);

/**
 * Check the required, trimmed string fields (`name`, `host`, `fromAddress`): present and at
 * most {@link MAX_FIELD_LENGTH} characters.
 *
 * @param {object} fields - Form field values.
 * @returns {object} The error map.
 */
function validateRequiredText(fields) {
  return [...REQUIRED_TEXT_FIELDS].reduce((errors, [field, label]) => {
    const value = (Object.getOwnPropertyDescriptor(fields, field)?.value || '').trim();
    const fieldErrors = value
      ? validateMaxLength(value, MAX_FIELD_LENGTH, field, label)
      : { [field]: `${label} is required` };

    return { ...errors, ...fieldErrors };
  }, {});
}

/**
 * Check the encryption select: one of {@link ENCRYPTIONS}.
 *
 * @param {string} encryption - The selected encryption.
 * @returns {object} The error map.
 */
function validateEncryption(encryption) {
  return ENCRYPTIONS.includes(encryption) ? {} : { encryption: 'Encryption is required' };
}

/**
 * Check the credentials: the trimmed username and the untrimmed password are at most
 * {@link MAX_FIELD_LENGTH} characters, and the resulting state pairs them (both set or both
 * empty). On edit, an empty password input keeps the stored one.
 *
 * @param {object} fields - Form field values.
 * @param {({hasPassword: boolean}|null)} stored - The stored credentials on edit; `null` on
 *   create.
 * @returns {object} The error map.
 */
function validateCredentials(fields, stored) {
  const username = fields.username.trim();
  const errors = {
    ...validateMaxLength(username, MAX_FIELD_LENGTH, 'username', 'Username'),
    ...validateMaxLength(fields.password, MAX_FIELD_LENGTH, 'password', 'Password'),
  };
  const hasPassword = fields.password !== '' || Boolean(stored?.hasPassword);

  if (!errors.password && (username !== '') !== hasPassword) {
    errors.password = PAIRING_MESSAGE;
  }

  return errors;
}

/**
 * Build the attributes shared by create and update: trimmed strings and a numeric port.
 *
 * @param {object} fields - Form field values.
 * @returns {object} The common attributes.
 */
function baseAttributes(fields) {
  return {
    name: fields.name.trim(),
    host: fields.host.trim(),
    port: Number(fields.port.trim()),
    encryption: fields.encryption,
    fromAddress: fields.fromAddress.trim(),
  };
}

/**
 * Validation and payload rules of the SMTP connection create / edit form. The API stays the
 * source of truth; these checks only spare a round trip. `password` is never trimmed.
 */
const SmtpConnectionFormRules = {
  /**
   * Validate the form.
   *
   * @param {{name: string, host: string, port: string, encryption: string, username: string,
   *   password: string, fromAddress: string}} fields - Form field values.
   * @param {({username: (string|null), hasPassword: boolean}|null)} stored - The stored
   *   credentials on edit; `null` on create.
   * @returns {object} A map of field name to error message, empty when the form is valid.
   */
  validate(fields, stored) {
    const errors = {
      ...validateRequiredText(fields),
      ...validatePort(fields.port),
      ...validateEncryption(fields.encryption),
      ...validateCredentials(fields, stored),
    };

    if (!errors.fromAddress) {
      Object.assign(errors, validateEmail(fields.fromAddress.trim(), 'fromAddress'));
    }

    return errors;
  },

  /**
   * Build the create payload: `username` / `password` only when filled.
   *
   * @param {object} fields - Form field values.
   * @returns {object} The attributes for `SmtpConnectionsClient.create`.
   */
  buildCreatePayload(fields) {
    return {
      ...baseAttributes(fields),
      username: fields.username.trim() || undefined,
      password: fields.password || undefined,
    };
  },

  /**
   * Build the update payload: every non-credential field, `username` only when changed
   * (`''` clears it) and `password` only when typed (an empty input keeps the stored one).
   *
   * @param {object} fields - Form field values.
   * @param {{username: (string|null)}} stored - The stored credentials.
   * @returns {object} The attributes for `SmtpConnectionsClient.update`.
   */
  buildUpdatePayload(fields, stored) {
    const username = fields.username.trim();

    return {
      ...baseAttributes(fields),
      username: username === (stored.username ?? '') ? undefined : username,
      password: fields.password || undefined,
    };
  },

  /**
   * Map `VALIDATION_FAILED` details to the form fields, by their leading field name; the
   * pairing message goes on `password`.
   *
   * @param {string[]} details - The API's validation messages.
   * @returns {{fieldErrors: object, unmatched: string[]}} The per-field messages (joined with
   *   `; `) and the details matching no field.
   */
  mapValidationDetails(details) {
    const byField = new Map();
    const unmatched = [];

    details.forEach((detail) => {
      const field = detail === PAIRING_MESSAGE ? 'password' : detail.split(' ')[0];

      if (FIELD_NAMES.has(field)) {
        byField.set(field, [...(byField.get(field) ?? []), detail]);
      } else {
        unmatched.push(detail);
      }
    });

    const fieldErrors = Object.fromEntries([...byField].map(([field, messages]) => [field, messages.join('; ')]));

    return { fieldErrors, unmatched };
  },
};

export default SmtpConnectionFormRules;
