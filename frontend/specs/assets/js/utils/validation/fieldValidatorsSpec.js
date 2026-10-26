import {
  EMAIL_PATTERN,
  MAX_ORGANIZATION_NAME_LENGTH,
  MIN_PASSWORD_LENGTH,
  validateEmail,
  validateOrganizationName,
  validatePasswordLength,
  validatePasswordConfirmation,
} from '../../../../../assets/js/utils/validation/fieldValidators.js';

describe('fieldValidators', () => {
  describe('constants', () => {
    it('exposes the minimum password length', () => {
      expect(MIN_PASSWORD_LENGTH).toBe(8);
    });

    it('exposes the maximum organization name length', () => {
      expect(MAX_ORGANIZATION_NAME_LENGTH).toBe(255);
    });

    it('exposes the email pattern', () => {
      expect(EMAIL_PATTERN.test('a@b.co')).toBe(true);
    });
  });

  describe('validateEmail', () => {
    it('returns no errors for a well-formed email', () => {
      expect(validateEmail('a@b.co')).toEqual({});
    });

    it('flags a malformed email', () => {
      expect(validateEmail('not-an-email')).toEqual({ email: 'Email is invalid' });
    });

    it('flags a blank email', () => {
      expect(validateEmail('')).toEqual({ email: 'Email is invalid' });
    });

    it('keys the error under a custom field name', () => {
      expect(validateEmail('nope', 'contact')).toEqual({ contact: 'Email is invalid' });
    });
  });

  describe('validatePasswordLength', () => {
    it('returns no errors for a password of the minimum length', () => {
      expect(validatePasswordLength('a'.repeat(MIN_PASSWORD_LENGTH))).toEqual({});
    });

    it('flags a password that is too short', () => {
      expect(validatePasswordLength('short')).toEqual({
        password: 'Password must be at least 8 characters',
      });
    });

    it('keys the error under a custom field name', () => {
      expect(validatePasswordLength('short', 'newPassword')).toEqual({
        newPassword: 'Password must be at least 8 characters',
      });
    });
  });

  describe('validatePasswordConfirmation', () => {
    it('returns no errors when the values match', () => {
      expect(validatePasswordConfirmation('secret', 'secret')).toEqual({});
    });

    it('flags a mismatch', () => {
      expect(validatePasswordConfirmation('secret', 'other')).toEqual({
        passwordConfirmation: 'Passwords do not match',
      });
    });

    it('flags a blank confirmation against a non-blank password', () => {
      expect(validatePasswordConfirmation('secret', '')).toEqual({
        passwordConfirmation: 'Passwords do not match',
      });
    });

    it('keys the error under a custom field name', () => {
      expect(validatePasswordConfirmation('secret', 'other', 'newPasswordConfirmation')).toEqual({
        newPasswordConfirmation: 'Passwords do not match',
      });
    });
  });

  describe('validateOrganizationName', () => {
    const required = { organizationName: 'Organization name is required' };
    const tooLong = { organizationName: 'Organization name must be at most 255 characters' };

    it('flags an empty name', () => {
      expect(validateOrganizationName('')).toEqual(required);
    });

    it('flags an undefined name', () => {
      expect(validateOrganizationName(undefined)).toEqual(required);
    });

    it('flags a whitespace-only name', () => {
      expect(validateOrganizationName('   ')).toEqual(required);
    });

    it('flags a name longer than 255 characters after trimming', () => {
      expect(validateOrganizationName(` ${'a'.repeat(256)} `)).toEqual(tooLong);
    });

    it('accepts a 1-character name', () => {
      expect(validateOrganizationName('a')).toEqual({});
    });

    it('accepts a 255-character name', () => {
      expect(validateOrganizationName('a'.repeat(255))).toEqual({});
    });

    it('accepts a 255-character name padded with whitespace', () => {
      expect(validateOrganizationName(`  ${'a'.repeat(255)}  `)).toEqual({});
    });

    it('accepts padded input', () => {
      expect(validateOrganizationName('  a  ')).toEqual({});
    });

    it('keys the error under a custom field name', () => {
      expect(validateOrganizationName('', 'org')).toEqual({ org: 'Organization name is required' });
    });
  });
});
