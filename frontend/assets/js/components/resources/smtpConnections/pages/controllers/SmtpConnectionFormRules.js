import {
  validateEmail, validateMaxLength, validatePort,
} from '../../../../../utils/validation/fieldValidators.js';

/** Maximum length of the SMTP connection string fields; mirrors the backend's limit. */
export const MAX_FIELD_LENGTH = 255;

/** The encryption modes the API accepts. */
export const ENCRYPTIONS = ['none', 'tls', 'ssl'];

/** The API's credential pairing message; shown on the password field. */
export const PAIRING_MESSAGE = 'username and password must be both set or both empty';

/** The API's credentials-require-encryption message; shown on the password field. */
export const ENCRYPTION_REQUIRED_MESSAGE = 'username and password require encryption tls or ssl';

/** The API's credential-redirection message; shown on the password field. */
export const REDIRECTION_MESSAGE = 'password must be sent again when host, port or encryption changes';

const SECURE_ENCRYPTIONS = new Set(['tls', 'ssl']);

const PASSWORD_MESSAGES = new Set([PAIRING_MESSAGE, ENCRYPTION_REQUIRED_MESSAGE, REDIRECTION_MESSAGE]);

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
 * Tell whether the form's host, port or encryption differ from the stored ones. The host is
 * compared trimmed and case-sensitively, the port as a number.
 *
 * @param {object} fields - Form field values.
 * @param {{host: string, port: number, encryption: string}} stored - The stored settings.
 * @returns {boolean} `true` when the destination of the credentials changes.
 */
function destinationChanged(fields, stored) {
  return fields.host.trim() !== stored.host
    || Number(fields.port.trim()) !== stored.port
    || fields.encryption !== stored.encryption;
}

/**
 * Tell whether the stored credentials would be sent to a new destination: on edit, credentials
 * are kept, no new password is typed, and the host, port or encryption changed.
 *
 * @param {object} fields - Form field values.
 * @param {(object|null)} stored - The stored settings on edit; `null` on create.
 * @param {boolean} hasCredentials - Whether the merged state keeps credentials.
 * @returns {boolean} `true` when the password must be sent again.
 */
function redirectsCredentials(fields, stored, hasCredentials) {
  return Boolean(stored) && hasCredentials && fields.password === '' && destinationChanged(fields, stored);
}

/**
 * Find the credential rule the merged state (form values over the stored ones) breaks, in
 * order: pairing, then encryption, then redirection.
 *
 * @param {object} fields - Form field values.
 * @param {(object|null)} stored - The stored settings on edit; `null` on create.
 * @param {string} username - The trimmed username.
 * @returns {(string|null)} The message of the first broken rule, or `null` when none is.
 */
function credentialRuleError(fields, stored, username) {
  const hasPassword = fields.password !== '' || Boolean(stored?.hasPassword);
  const hasCredentials = username !== '' || hasPassword;

  if ((username !== '') !== hasPassword) {
    return PAIRING_MESSAGE;
  }

  if (hasCredentials && !SECURE_ENCRYPTIONS.has(fields.encryption)) {
    return ENCRYPTION_REQUIRED_MESSAGE;
  }

  return redirectsCredentials(fields, stored, hasCredentials) ? REDIRECTION_MESSAGE : null;
}

/**
 * Check the credentials: the trimmed username and the untrimmed password are at most
 * {@link MAX_FIELD_LENGTH} characters; then, when the password has no error yet, the resulting
 * state pairs them (both set or both empty), credentials require `tls` / `ssl`, and on edit a
 * changed host, port or encryption requires the password again. On edit, an empty password
 * input keeps the stored one.
 *
 * @param {object} fields - Form field values.
 * @param {({hasPassword: boolean, host: string, port: number, encryption: string}|null)} stored
 *   - The stored settings on edit; `null` on create.
 * @returns {object} The error map.
 */
function validateCredentials(fields, stored) {
  const username = fields.username.trim();
  const errors = {
    ...validateMaxLength(username, MAX_FIELD_LENGTH, 'username', 'Username'),
    ...validateMaxLength(fields.password, MAX_FIELD_LENGTH, 'password', 'Password'),
  };

  if (!errors.password) {
    const ruleError = credentialRuleError(fields, stored, username);

    if (ruleError) {
      errors.password = ruleError;
    }
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
   * @param {({username: (string|null), hasPassword: boolean, host: string, port: number,
   *   encryption: string}|null)} stored - The stored credentials and settings on edit; `null`
   *   on create.
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
   * pairing, encryption-required and redirection messages go on `password`.
   *
   * @param {string[]} details - The API's validation messages.
   * @returns {{fieldErrors: object, unmatched: string[]}} The per-field messages (joined with
   *   `; `) and the details matching no field.
   */
  mapValidationDetails(details) {
    const byField = new Map();
    const unmatched = [];

    details.forEach((detail) => {
      const field = PASSWORD_MESSAGES.has(detail) ? 'password' : detail.split(' ')[0];

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
