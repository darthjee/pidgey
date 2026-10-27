/** Pattern an email address must match to be considered well-formed. */
export const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** Minimum password length; mirrors the backend's limit. */
export const MIN_PASSWORD_LENGTH = 8;

/** Maximum organization name length (after trimming); mirrors the backend's limit. */
export const MAX_ORGANIZATION_NAME_LENGTH = 255;

/**
 * Check that an email is well-formed. Only the format is checked; callers decide whether a
 * blank email is an error.
 *
 * @description Returns an error map so the result can be spread into a controller's errors.
 * @param {string} email - The email value to check.
 * @param {string} [field] - The field name the error is keyed under.
 * @returns {object} `{ [field]: 'Email is invalid' }` when malformed, otherwise `{}`.
 */
export function validateEmail(email, field = 'email') {
  return EMAIL_PATTERN.test(email) ? {} : { [field]: 'Email is invalid' };
}

/**
 * Check that a password meets the minimum length.
 *
 * @description Returns an error map so the result can be spread into a controller's errors.
 * @param {string} password - The password value to check.
 * @param {string} [field] - The field name the error is keyed under.
 * @returns {object} `{ [field]: message }` when shorter than {@link MIN_PASSWORD_LENGTH},
 *   otherwise `{}`.
 */
export function validatePasswordLength(password, field = 'password') {
  if (password.length >= MIN_PASSWORD_LENGTH) {
    return {};
  }

  return { [field]: `Password must be at least ${MIN_PASSWORD_LENGTH} characters` };
}

/**
 * Check that a password confirmation matches the password. Only the match is checked; callers
 * decide whether a blank confirmation is an error.
 *
 * @description Returns an error map so the result can be spread into a controller's errors.
 * @param {string} password - The password value.
 * @param {string} confirmation - The confirmation value to compare against the password.
 * @param {string} [field] - The field name the error is keyed under.
 * @returns {object} `{ [field]: 'Passwords do not match' }` when they differ, otherwise `{}`.
 */
export function validatePasswordConfirmation(password, confirmation, field = 'passwordConfirmation') {
  return password === confirmation ? {} : { [field]: 'Passwords do not match' };
}

/**
 * Check that an organization name is present and not too long. The value is trimmed before
 * checking, mirroring the backend, which trims it on receipt.
 *
 * @description Returns an error map so the result can be spread into a controller's errors.
 * @param {string} [name] - The organization name value to check.
 * @param {string} [field] - The field name the error is keyed under.
 * @returns {object} `{ [field]: message }` when blank or longer than
 *   {@link MAX_ORGANIZATION_NAME_LENGTH} after trimming, otherwise `{}`.
 */
export function validateOrganizationName(name, field = 'organizationName') {
  const trimmed = (name || '').trim();

  if (!trimmed) {
    return { [field]: 'Organization name is required' };
  }

  if (trimmed.length > MAX_ORGANIZATION_NAME_LENGTH) {
    return {
      [field]: `Organization name must be at most ${MAX_ORGANIZATION_NAME_LENGTH} characters`,
    };
  }

  return {};
}

/** Lowest valid TCP port. */
export const MIN_PORT = 1;

/** Highest valid TCP port. */
export const MAX_PORT = 65535;

/**
 * Check that a value is at most `max` characters long. The value is checked as given; callers
 * trim it first when the backend trims it too.
 *
 * @description Returns an error map so the result can be spread into a controller's errors.
 * @param {string} value - The value to check.
 * @param {number} max - The maximum length.
 * @param {string} field - The field name the error is keyed under.
 * @param {string} label - The field label used in the message.
 * @returns {object} `{ [field]: message }` when longer than `max`, otherwise `{}`.
 */
export function validateMaxLength(value, max, field, label) {
  return value.length > max ? { [field]: `${label} must be at most ${max} characters` } : {};
}

/**
 * Check that a TCP port, as typed in a form, is present and an integer between
 * {@link MIN_PORT} and {@link MAX_PORT}. The value is trimmed before checking.
 *
 * @description Returns an error map so the result can be spread into a controller's errors.
 * @param {string} value - The port input value.
 * @param {string} [field] - The field name the error is keyed under.
 * @returns {object} `{ [field]: message }` when blank, not an integer or out of range,
 *   otherwise `{}`.
 */
export function validatePort(value, field = 'port') {
  const trimmed = (value || '').trim();

  if (!trimmed) {
    return { [field]: 'Port is required' };
  }

  if (!/^-?\d+$/.test(trimmed)) {
    return { [field]: 'port must be an integer' };
  }

  const port = Number(trimmed);

  if (port < MIN_PORT || port > MAX_PORT) {
    return { [field]: `port must be between ${MIN_PORT} and ${MAX_PORT}` };
  }

  return {};
}

/** Maximum API token name length (after trimming); mirrors the backend's limit. */
export const MAX_API_TOKEN_NAME_LENGTH = 255;

/**
 * Check that an API token name is present and not too long. The value is trimmed before
 * checking, mirroring the backend, which trims it on receipt.
 *
 * @description Returns an error map so the result can be spread into a controller's errors.
 * @param {string} [name] - The API token name value to check.
 * @param {string} [field] - The field name the error is keyed under.
 * @returns {object} `{ [field]: message }` when blank or longer than
 *   {@link MAX_API_TOKEN_NAME_LENGTH} after trimming, otherwise `{}`.
 */
export function validateApiTokenName(name, field = 'name') {
  const trimmed = (name || '').trim();

  if (!trimmed) {
    return { [field]: 'Name is required' };
  }

  return validateMaxLength(trimmed, MAX_API_TOKEN_NAME_LENGTH, field, 'Name');
}

/**
 * Check that at least one scope is selected.
 *
 * @description Returns an error map so the result can be spread into a controller's errors.
 * @param {string[]} [scopes] - The selected scope names.
 * @param {string} [field] - The field name the error is keyed under.
 * @returns {object} `{ [field]: 'Select at least one scope' }` when none is selected,
 *   otherwise `{}`.
 */
export function validateScopes(scopes, field = 'scopes') {
  return scopes?.length ? {} : { [field]: 'Select at least one scope' };
}
