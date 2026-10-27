import React from 'react';
import ApiTokenCreatedPanel from '../../../../../../../../assets/js/components/resources/apiTokens/pages/tokens/ApiTokenCreatedPanel.jsx';
import {
  COPY_DONE, COPY_FAILED, COPY_IDLE,
} from '../../../../../../../../assets/js/components/resources/apiTokens/pages/controllers/ApiTokenNewController.js';
import { renderedOutput } from '../../../../../../../support/renderedOutput.js';

describe('ApiTokenCreatedPanel', () => {
  const token = 'pdg_plain-secret-token';
  let onCopy;

  beforeEach(() => {
    onCopy = jasmine.createSpy('onCopy');
  });

  const render = (copyState = COPY_IDLE) => renderedOutput(
    React.createElement(ApiTokenCreatedPanel, { token, copyState, onCopy }),
  );

  it('shows the token read-only with the warning', () => {
    const panel = render();

    expect(panel.containsAttribute('value', token)).withContext('token').toBeTrue();
    expect(panel.contains('readOnly=""')).withContext('read-only').toBeTrue();
    expect(panel.contains('Copy this token now. It cannot be shown again.')).withContext('warning').toBeTrue();
  });

  it('renders the Copy button and the Done link back to the list', () => {
    const panel = render();

    expect(panel.containsElement('button', 'Copy')).withContext('copy').toBeTrue();
    expect(panel.containsAttribute('href', '#/api-tokens')).withContext('href').toBeTrue();
    expect(panel.containsElement('a', 'Done')).withContext('done').toBeTrue();
  });

  it('shows no copy status before any copy', () => {
    expect(render().contains('role="status"')).toBeFalse();
  });

  it('confirms a successful copy', () => {
    expect(render(COPY_DONE).contains('Copied to the clipboard.')).toBeTrue();
  });

  it('shows the manual-copy fallback when copying failed', () => {
    expect(render(COPY_FAILED).contains('Copy failed — select the token and copy it manually')).toBeTrue();
  });

  it('wires the Copy click handler', () => {
    const element = ApiTokenCreatedPanel({ token, copyState: COPY_IDLE, onCopy });
    const button = element.props.children[2];

    expect(button.props.onClick).toBe(onCopy);
  });
});
