import SmtpConnectionForm from './SmtpConnectionForm.jsx';

/**
 * SMTP connection create page (`#/smtp-connections/new`): the shared form, with no id.
 *
 * @returns {React.ReactElement} The rendered create page.
 */
export default function SmtpConnectionNew() {
  return <SmtpConnectionForm />;
}
