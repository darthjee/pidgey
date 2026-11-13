import ApiError from '../../assets/js/client/ApiError.js';
import loadFixture, { loadApiError } from './loadFixture.js';

describe('loadFixture', () => {
  it('returns the parsed fixture', () => {
    expect(loadFixture('smtpConnections/item')).toEqual({
      smtpConnection: {
        id: 12,
        name: 'Mailpit',
        host: 'mailpit',
        port: 1025,
        encryption: 'none',
        username: null,
        hasPassword: false,
        fromAddress: 'noreply@example.com',
        active: true,
        createdAt: '2026-10-17T12:00:00.000Z',
        updatedAt: '2026-10-20T09:30:00.000Z',
      },
    });
  });

  it('returns a fresh copy on each call', () => {
    const first = loadFixture('smtpConnections/item');
    const second = loadFixture('smtpConnections/item');

    first.smtpConnection.name = 'Changed';

    expect(second).not.toBe(first);
    expect(second.smtpConnection.name).toEqual('Mailpit');
  });

  it('throws on an unknown fixture', () => {
    expect(() => loadFixture('smtpConnections/unknown')).toThrow();
  });

  describe('loadApiError', () => {
    it('builds the ApiError of an error envelope', () => {
      const error = loadApiError('smtpConnections/smtpFailure');

      expect(error).toEqual(jasmine.any(ApiError));
      expect(error.status).toBe(502);
      expect(error.message).toBe('The SMTP server rejected the credentials');
      expect(error.code).toBe('SMTP_AUTH_FAILED');
      expect(error.details).toBeUndefined();
      expect(error.smtp).toEqual({ code: 535, message: '5.7.8 Authentication credentials invalid' });
    });

    it('carries the validation details', () => {
      expect(loadApiError('smtpConnections/validationFailed').details).toEqual([
        'port must be between 1 and 65535',
        'username and password require encryption tls or ssl',
      ]);
    });
  });
});
