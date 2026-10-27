import React from 'react';
import NoActiveConnectionNotice from '../../../../../../../../assets/js/components/resources/smtpConnections/pages/connections/NoActiveConnectionNotice.jsx';
import { renderedOutput } from '../../../../../../../support/renderedOutput.js';

describe('NoActiveConnectionNotice', () => {
  const render = (connections) => renderedOutput(
    React.createElement(NoActiveConnectionNotice, { connections }),
  );

  it('warns that sending is disabled when no connection is active', () => {
    const page = render([{ id: 1, active: false }]);

    expect(page.contains('sending email is disabled until one is activated')).toBeTrue();
  });

  it('warns when there is no connection at all', () => {
    expect(render([]).contains('alert-warning')).toBeTrue();
  });

  it('renders nothing when a connection is active', () => {
    expect(render([{ id: 1, active: false }, { id: 2, active: true }]).isEmpty()).toBeTrue();
  });
});
