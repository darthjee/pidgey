import ApiTokenFormRules, {
  API_TOKEN_SCOPES,
} from '../../../../../../../../assets/js/components/resources/apiTokens/pages/controllers/ApiTokenFormRules.js';

describe('ApiTokenFormRules', () => {
  it('exposes the known scopes', () => {
    expect(API_TOKEN_SCOPES).toEqual(['send']);
  });

  describe('.validate', () => {
    it('accepts a name and one scope', () => {
      expect(ApiTokenFormRules.validate({ name: ' Shop ', scopes: ['send'] })).toEqual({});
    });

    it('flags a blank name and an empty scope selection', () => {
      expect(ApiTokenFormRules.validate({ name: '  ', scopes: [] })).toEqual({
        name: 'Name is required', scopes: 'Select at least one scope',
      });
    });

    it('flags a name longer than 255 characters', () => {
      expect(ApiTokenFormRules.validate({ name: 'a'.repeat(256), scopes: ['send'] })).toEqual({
        name: 'Name must be at most 255 characters',
      });
    });
  });

  describe('.mapValidationDetails', () => {
    it('maps the details to the name and scopes fields', () => {
      const result = ApiTokenFormRules.mapValidationDetails([
        'name should not be empty',
        'name must be shorter than or equal to 255 characters',
        'scopes should not be empty',
        'each value in scopes must be one of the following values: send',
      ]);

      expect(result).toEqual({
        fieldErrors: {
          name: 'name should not be empty; name must be shorter than or equal to 255 characters',
          scopes: 'scopes should not be empty; each value in scopes must be one of the following values: send',
        },
        unmatched: [],
      });
    });

    it('keeps the details matching no field apart', () => {
      expect(ApiTokenFormRules.mapValidationDetails(['property foo should not exist'])).toEqual({
        fieldErrors: {}, unmatched: ['property foo should not exist'],
      });
    });
  });
});
