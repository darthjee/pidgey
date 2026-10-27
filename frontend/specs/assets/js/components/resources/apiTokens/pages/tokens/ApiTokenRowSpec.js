import React from 'react';
import ApiTokenRow from '../../../../../../../../assets/js/components/resources/apiTokens/pages/tokens/ApiTokenRow.jsx';
import { renderedOutput } from '../../../../../../../support/renderedOutput.js';

describe('ApiTokenRow', () => {
  const active = {
    id: 1, name: 'Shop backend', tokenPrefix: 'pdg_', scopes: ['send', 'other'],
    createdAt: '2026-10-17T12:00:00.000Z', lastUsedAt: '2026-10-20T09:30:00.000Z', revokedAt: null,
  };
  const revoked = { ...active, id: 2, lastUsedAt: null, revokedAt: '2026-10-21T08:00:00.000Z' };
  const localized = (value) => new Date(value).toLocaleString();

  let onRevoke;

  beforeEach(() => {
    onRevoke = jasmine.createSpy('onRevoke');
  });

  const render = (token) => renderedOutput(
    React.createElement('table', null, React.createElement('tbody', null,
      React.createElement(ApiTokenRow, { token, onRevoke }))),
  );

  it('renders the name, prefix, scopes and dates', () => {
    const row = render(active);

    expect(row.containsElement('td', 'Shop backend')).withContext('name').toBeTrue();
    expect(row.contains('<code>pdg_</code>…')).withContext('prefix').toBeTrue();
    expect(row.containsElement('td', 'send, other')).withContext('scopes').toBeTrue();
    expect(row.containsElement('td', localized(active.createdAt))).withContext('created').toBeTrue();
    expect(row.containsElement('td', localized(active.lastUsedAt))).withContext('last used').toBeTrue();
  });

  it('renders Never when the token was never used', () => {
    expect(render(revoked).containsElement('td', 'Never')).toBeTrue();
  });

  it('renders a Revoke button on an active token', () => {
    const row = render(active);

    expect(row.containsElement('button', 'Revoke')).withContext('button').toBeTrue();
    expect(row.contains('Revoked')).withContext('badge').toBeFalse();
  });

  it('renders the Revoked badge with its date and no Revoke button on a revoked token', () => {
    const row = render(revoked);

    expect(row.contains('>Revoked<')).withContext('badge').toBeTrue();
    expect(row.containsElement('span', localized(revoked.revokedAt))).withContext('date').toBeTrue();
    expect(row.containsTag('button')).withContext('button').toBeFalse();
  });

  it('wires the click handler', () => {
    const element = ApiTokenRow({ token: active, onRevoke });
    const button = element.props.children[5].props.children;

    expect(button.props.onClick).toBe(onRevoke);
  });
});
