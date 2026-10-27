import React from 'react';
import RemoveCredentialsButton from '../../../../../../../../assets/js/components/resources/smtpConnections/pages/connections/RemoveCredentialsButton.jsx';
import { renderedOutput } from '../../../../../../../support/renderedOutput.js';

describe('RemoveCredentialsButton', () => {
  let onRemoveCredentials;

  const render = (stored) => renderedOutput(
    React.createElement(RemoveCredentialsButton, { stored, onRemoveCredentials }),
  );

  beforeEach(() => {
    onRemoveCredentials = jasmine.createSpy('onRemoveCredentials');
  });

  it('renders the button when credentials are stored', () => {
    expect(render({ username: 'user', hasPassword: true }).containsElement('button', 'Remove credentials')).toBeTrue();
  });

  it('renders the button when only a password is stored', () => {
    expect(render({ username: null, hasPassword: true }).isEmpty()).toBeFalse();
  });

  it('renders the button when only a username is stored', () => {
    expect(render({ username: 'user', hasPassword: false }).isEmpty()).toBeFalse();
  });

  it('renders nothing without stored credentials', () => {
    expect(render({ username: null, hasPassword: false }).isEmpty()).toBeTrue();
  });

  it('renders nothing on create', () => {
    expect(render(null).isEmpty()).toBeTrue();
  });

  it('wires the click handler', () => {
    const element = RemoveCredentialsButton({ stored: { username: 'user', hasPassword: true }, onRemoveCredentials });

    expect(element.props.onClick).toBe(onRemoveCredentials);
  });
});
