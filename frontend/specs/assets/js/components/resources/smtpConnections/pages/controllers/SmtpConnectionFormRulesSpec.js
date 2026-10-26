import SmtpConnectionFormRules, {
  ENCRYPTIONS, MAX_FIELD_LENGTH, PAIRING_MESSAGE,
} from '../../../../../../../../assets/js/components/resources/smtpConnections/pages/controllers/SmtpConnectionFormRules.js';

describe('SmtpConnectionFormRules', () => {
  const buildFields = (overrides = {}) => ({
    name: 'Relay', host: 'smtp.example.com', port: '587', encryption: 'tls',
    username: '', password: '', fromAddress: 'noreply@example.com', ...overrides,
  });

  it('exposes the limits and the encryption modes', () => {
    expect(MAX_FIELD_LENGTH).toBe(255);
    expect(ENCRYPTIONS).toEqual(['none', 'tls', 'ssl']);
  });

  describe('.validate', () => {
    const validate = (overrides, stored = null) => SmtpConnectionFormRules.validate(buildFields(overrides), stored);

    it('accepts a valid form without credentials', () => {
      expect(validate({})).toEqual({});
    });

    it('accepts a valid form with both credentials', () => {
      expect(validate({ username: 'user', password: 's3cret' })).toEqual({});
    });

    it('requires the trimmed name, host and from address', () => {
      expect(validate({ name: '  ', host: '', fromAddress: ' ' })).toEqual({
        name: 'Name is required', host: 'Host is required', fromAddress: 'From address is required',
      });
    });

    it('requires the port and the encryption', () => {
      expect(validate({ port: '', encryption: '' })).toEqual({
        port: 'Port is required', encryption: 'Encryption is required',
      });
    });

    it('rejects an unknown encryption', () => {
      expect(validate({ encryption: 'starttls' })).toEqual({ encryption: 'Encryption is required' });
    });

    it('checks the port is an integer in range', () => {
      expect(validate({ port: '587.5' })).toEqual({ port: 'port must be an integer' });
      expect(validate({ port: '70000' })).toEqual({ port: 'port must be between 1 and 65535' });
    });

    it('limits the string fields to 255 characters after trimming', () => {
      const long = 'a'.repeat(256);

      expect(validate({ name: ` ${'a'.repeat(255)} ` })).toEqual({});
      expect(validate({ name: long, host: long })).toEqual({
        name: 'Name must be at most 255 characters', host: 'Host must be at most 255 characters',
      });
      expect(validate({ username: long, password: 'x' }).username).toBe('Username must be at most 255 characters');
    });

    it('limits the untrimmed password to 255 characters', () => {
      expect(validate({ username: 'user', password: `${'a'.repeat(255)} ` }))
        .toEqual({ password: 'Password must be at most 255 characters' });
    });

    it('checks the from address is an email', () => {
      expect(validate({ fromAddress: 'nope' })).toEqual({ fromAddress: 'Email is invalid' });
    });

    it('flags an overlong from address once', () => {
      expect(validate({ fromAddress: `${'a'.repeat(250)}@example.com` }))
        .toEqual({ fromAddress: 'From address must be at most 255 characters' });
    });

    it('requires both credentials or none on create', () => {
      expect(validate({ username: 'user' })).toEqual({ password: PAIRING_MESSAGE });
      expect(validate({ password: 's3cret' })).toEqual({ password: PAIRING_MESSAGE });
    });

    it('never trims the password for the pairing check', () => {
      expect(validate({ username: 'user', password: ' ' })).toEqual({});
    });

    it('accepts a username alone on edit when a password is stored', () => {
      expect(validate({ username: 'user' }, { username: 'old', hasPassword: true })).toEqual({});
    });

    it('rejects a username alone on edit when no password is stored', () => {
      expect(validate({ username: 'user' }, { username: null, hasPassword: false })).toEqual({ password: PAIRING_MESSAGE });
    });

    it('rejects a cleared username on edit while a password is stored', () => {
      expect(validate({ username: '' }, { username: 'user', hasPassword: true })).toEqual({ password: PAIRING_MESSAGE });
    });
  });

  describe('.buildCreatePayload', () => {
    it('trims the strings and sends the port as a number, without empty credentials', () => {
      expect(SmtpConnectionFormRules.buildCreatePayload(buildFields({ name: ' Relay ', port: ' 587 ' }))).toEqual({
        name: 'Relay', host: 'smtp.example.com', port: 587, encryption: 'tls',
        fromAddress: 'noreply@example.com', username: undefined, password: undefined,
      });
    });

    it('sends the trimmed username and the untrimmed password when filled', () => {
      const payload = SmtpConnectionFormRules.buildCreatePayload(buildFields({ username: ' user ', password: ' s3cret ' }));

      expect(payload.username).toBe('user');
      expect(payload.password).toBe(' s3cret ');
    });
  });

  describe('.buildUpdatePayload', () => {
    const stored = { username: 'user', hasPassword: true };

    it('sends the non-credential fields, omitting an unchanged username and an empty password', () => {
      expect(SmtpConnectionFormRules.buildUpdatePayload(buildFields({ username: 'user' }), stored)).toEqual({
        name: 'Relay', host: 'smtp.example.com', port: 587, encryption: 'tls',
        fromAddress: 'noreply@example.com', username: undefined, password: undefined,
      });
    });

    it('sends the password only when typed', () => {
      const payload = SmtpConnectionFormRules.buildUpdatePayload(buildFields({ username: 'user', password: 'n3w' }), stored);

      expect(payload.password).toBe('n3w');
    });

    it('sends a changed username, trimmed', () => {
      expect(SmtpConnectionFormRules.buildUpdatePayload(buildFields({ username: ' other ' }), stored).username).toBe('other');
    });

    it('sends an emptied username as an empty string', () => {
      expect(SmtpConnectionFormRules.buildUpdatePayload(buildFields({ username: '' }), stored).username).toBe('');
    });

    it('treats an empty username as unchanged when none is stored', () => {
      expect(SmtpConnectionFormRules.buildUpdatePayload(buildFields(), { username: null, hasPassword: false }).username)
        .toBeUndefined();
    });
  });

  describe('.mapValidationDetails', () => {
    it('maps each detail to its leading field, joining several', () => {
      expect(SmtpConnectionFormRules.mapValidationDetails([
        'port must be an integer', 'port must be between 1 and 65535', 'fromAddress must be an email',
      ])).toEqual({
        fieldErrors: { port: 'port must be an integer; port must be between 1 and 65535', fromAddress: 'fromAddress must be an email' },
        unmatched: [],
      });
    });

    it('puts the pairing message on the password field', () => {
      expect(SmtpConnectionFormRules.mapValidationDetails([PAIRING_MESSAGE]))
        .toEqual({ fieldErrors: { password: PAIRING_MESSAGE }, unmatched: [] });
    });

    it('keeps the details matching no field', () => {
      expect(SmtpConnectionFormRules.mapValidationDetails(['body must be an object', 'host must not be empty']))
        .toEqual({ fieldErrors: { host: 'host must not be empty' }, unmatched: ['body must be an object'] });
    });
  });
});
