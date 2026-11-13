import React from 'react';
import SmtpReply from '../../../../../../../../assets/js/components/resources/smtpConnections/pages/connections/SmtpReply.jsx';
import { renderedOutput } from '../../../../../../../support/renderedOutput.js';
import loadFixture from '../../../../../../../support/loadFixture.js';

describe('SmtpReply', () => {
  const render = (smtp) => renderedOutput(React.createElement(SmtpReply, { smtp }));

  it('renders the code and the message in a wrapping span', () => {
    const page = render(loadFixture('smtpConnections/testSent').smtp);

    expect(page.containsElement('span', '250 OK: queued as 1a2b3c')).withContext('reply').toBeTrue();
    expect(page.containsAttribute('class', 'text-break')).withContext('class').toBeTrue();
  });

  it('renders a long reply in full', () => {
    const message = 'x'.repeat(600);

    expect(render({ code: 550, message }).containsElement('span', `550 ${message}`)).toBeTrue();
  });

  it('renders markup in the reply as literal text', () => {
    const page = render({ code: 550, message: '<b>x</b>' });

    expect(page.contains('550 &lt;b&gt;x&lt;/b&gt;')).withContext('escaped').toBeTrue();
    expect(page.contains('<b>')).withContext('no tag').toBeFalse();
  });
});
