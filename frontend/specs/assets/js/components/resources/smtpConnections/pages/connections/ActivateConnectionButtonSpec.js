import React from 'react';
import ActivateConnectionButton from '../../../../../../../../assets/js/components/resources/smtpConnections/pages/connections/ActivateConnectionButton.jsx';
import { renderedOutput } from '../../../../../../../support/renderedOutput.js';

describe('ActivateConnectionButton', () => {
  let onActivate;

  beforeEach(() => {
    onActivate = jasmine.createSpy('onActivate');
  });

  const render = (connection) => renderedOutput(
    React.createElement(ActivateConnectionButton, { connection, onActivate }),
  );

  it('renders an Activate button on an inactive connection', () => {
    expect(render({ id: 1, active: false }).containsElement('button', 'Activate')).toBeTrue();
  });

  it('renders nothing on the active connection', () => {
    expect(render({ id: 1, active: true }).isEmpty()).toBeTrue();
  });

  it('wires the click handler', () => {
    const element = ActivateConnectionButton({ connection: { id: 1, active: false }, onActivate });

    expect(element.props.onClick).toBe(onActivate);
  });
});
