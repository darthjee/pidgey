/**
 * An SMTP server reply, formatted as `code message`.
 *
 * @description Rendered as a plain text child (never as HTML) and never truncated; the
 * `text-break` class lets long replies wrap inside the alert that holds them.
 * @param {object} props - Component props.
 * @param {{code: number, message: string}} props.smtp - The SMTP server reply.
 * @returns {React.ReactElement} The rendered reply.
 */
export default function SmtpReply({ smtp }) {
  return <span className="text-break">{`${smtp.code} ${smtp.message}`}</span>;
}
