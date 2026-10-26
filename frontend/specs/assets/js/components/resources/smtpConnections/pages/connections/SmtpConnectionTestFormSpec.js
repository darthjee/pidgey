import React from 'react';
import SmtpConnectionTestForm from '../../../../../../../../assets/js/components/resources/smtpConnections/pages/connections/SmtpConnectionTestForm.jsx';
import { CLOSED_TEST_STATE } from '../../../../../../../../assets/js/components/resources/smtpConnections/pages/controllers/SmtpConnectionTestController.js';
import { renderedOutput } from '../../../../../../../support/renderedOutput.js';

describe('SmtpConnectionTestForm', () => {
  const connection = { id: 1, name: 'Relay' };
  const render = (overrides = {}) => renderedOutput(React.createElement(SmtpConnectionTestForm, {
    state: { ...CLOSED_TEST_STATE, connection, ...overrides },
    onClose: jasmine.createSpy('onClose'),
    onRecipientChange: jasmine.createSpy('onRecipientChange'),
    onSubmit: jasmine.createSpy('onSubmit'),
  }));

  it('names the connection under test', () => {
    expect(render().contains('Sends a test email through Relay.')).toBeTrue();
  });

  it('renders the optional recipient field, stating the default recipient', () => {
    const page = render({ to: 'bob@example.com' });

    expect(page.containsElement('label', 'Recipient')).withContext('label').toBeTrue();
    expect(page.containsAttribute('value', 'bob@example.com')).withContext('value').toBeTrue();
    expect(page.contains('Leave empty to send the test email to your own email address.')).withContext('help').toBeTrue();
  });

  it('renders an enabled Send button when idle', () => {
    const page = render();

    expect(page.containsElement('button', 'Send')).withContext('label').toBeTrue();
    expect(page.contains('disabled')).withContext('disabled').toBeFalse();
  });

  it('disables the Send button while sending', () => {
    const page = render({ sending: true });

    expect(page.containsElement('button', 'Sending…')).withContext('label').toBeTrue();
    expect(page.contains('disabled=""')).withContext('disabled').toBeTrue();
  });

  it('shows the success with the SMTP reply', () => {
    const page = render({ result: { code: 250, message: 'OK: queued' } });

    expect(page.contains('alert-success')).withContext('alert').toBeTrue();
    expect(page.contains('Test email sent. SMTP reply: 250 OK: queued')).withContext('reply').toBeTrue();
  });

  it('shows the error with the SMTP reply when present', () => {
    const page = render({ error: 'SMTP authentication failed', smtp: { code: 535, message: 'Auth failed' } });

    expect(page.contains('alert-danger')).withContext('alert').toBeTrue();
    expect(page.contains('SMTP authentication failed')).withContext('message').toBeTrue();
    expect(page.contains('SMTP reply: 535 Auth failed')).withContext('reply').toBeTrue();
  });

  it('shows the error without an SMTP reply when absent', () => {
    const page = render({ error: 'SMTP timeout' });

    expect(page.contains('SMTP timeout')).withContext('message').toBeTrue();
    expect(page.contains('SMTP reply')).withContext('reply').toBeFalse();
  });

  it('shows no outcome before the first attempt', () => {
    expect(render().contains('alert')).toBeFalse();
  });

  it('shows the validation error on the recipient field', () => {
    const page = render({ fieldError: 'to must be an email' });

    expect(page.contains('is-invalid')).withContext('invalid').toBeTrue();
    expect(page.containsElement('div', 'to must be an email')).withContext('feedback').toBeTrue();
  });
});
