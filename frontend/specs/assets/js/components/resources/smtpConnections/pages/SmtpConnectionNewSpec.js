import SmtpConnectionNew from '../../../../../../../assets/js/components/resources/smtpConnections/pages/SmtpConnectionNew.jsx';
import SmtpConnectionForm from '../../../../../../../assets/js/components/resources/smtpConnections/pages/SmtpConnectionForm.jsx';

describe('SmtpConnectionNew', () => {
  it('renders the shared form without an id', () => {
    const element = SmtpConnectionNew();

    expect(element.type).toBe(SmtpConnectionForm);
    expect(element.props.id).toBeUndefined();
  });
});
