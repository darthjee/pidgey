import { validateApiTokenName, validateScopes } from '../../../../../utils/validation/fieldValidators.js';

/** The scope names the API accepts (v1: only `send`). */
export const API_TOKEN_SCOPES = ['send'];

/**
 * Tell which form field a `VALIDATION_FAILED` detail belongs to: `name` when it starts with
 * `name`, `scopes` when it mentions `scopes` (e.g. `each value in scopes must be …`).
 *
 * @param {string} detail - One of the API's validation messages.
 * @returns {(string|null)} The field name, or `null` when it matches no field.
 */
function fieldOf(detail) {
  if (detail.split(' ')[0] === 'name') {
    return 'name';
  }

  return /\bscopes\b/.test(detail) ? 'scopes' : null;
}

/**
 * Validation and error-mapping rules of the API token create form. The API stays the source
 * of truth; these checks only spare a round trip.
 */
const ApiTokenFormRules = {
  /**
   * Validate the form: the trimmed name is 1–255 characters and at least one scope is
   * selected.
   *
   * @param {{name: string, scopes: string[]}} fields - Form field values.
   * @returns {object} A map of field name to error message, empty when the form is valid.
   */
  validate(fields) {
    return { ...validateApiTokenName(fields.name), ...validateScopes(fields.scopes) };
  },

  /**
   * Map `VALIDATION_FAILED` details to the `name` / `scopes` fields.
   *
   * @param {string[]} details - The API's validation messages.
   * @returns {{fieldErrors: object, unmatched: string[]}} The per-field messages (joined with
   *   `; `) and the details matching no field.
   */
  mapValidationDetails(details) {
    const byField = new Map();
    const unmatched = [];

    details.forEach((detail) => {
      const field = fieldOf(detail);

      if (field) {
        byField.set(field, [...(byField.get(field) ?? []), detail]);
      } else {
        unmatched.push(detail);
      }
    });

    const fieldErrors = Object.fromEntries([...byField].map(([field, messages]) => [field, messages.join('; ')]));

    return { fieldErrors, unmatched };
  },
};

export default ApiTokenFormRules;
