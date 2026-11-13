import React from 'react';
import DeactivateConnectionButton from '../../../../../../../../assets/js/components/resources/smtpConnections/pages/connections/DeactivateConnectionButton.jsx';
import { renderedOutput } from '../../../../../../../support/renderedOutput.js';
import loadFixture from '../../../../../../../support/loadFixture.js';

describe('DeactivateConnectionButton', () => {
  const [active, inactive] = loadFixture('smtpConnections/list').smtpConnections;
  let onDeactivate;

  beforeEach(() => {
    onDeactivate = jasmine.createSpy('onDeactivate');
  });

  const render = (connection) => renderedOutput(
    React.createElement(DeactivateConnectionButton, { connection, onDeactivate }),
  );

  it('renders a Deactivate button on the active connection', () => {
    const page = render(active);

    expect(page.containsElement('button', 'Deactivate')).withContext('label').toBeTrue();
    expect(page.contains('btn btn-sm btn-warning me-2')).withContext('class').toBeTrue();
  });

  it('renders nothing on an inactive connection', () => {
    expect(render(inactive).isEmpty()).toBeTrue();
  });

  it('wires the click handler', () => {
    const element = DeactivateConnectionButton({ connection: active, onDeactivate });

    expect(element.props.onClick).toBe(onDeactivate);
  });
});
