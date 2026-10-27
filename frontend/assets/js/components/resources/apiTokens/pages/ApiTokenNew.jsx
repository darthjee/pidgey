import { useMemo, useState } from 'react';
import ApiTokenNewController, { COPY_IDLE } from './controllers/ApiTokenNewController.js';
import ApiTokenNewHelper from './helpers/ApiTokenNewHelper.jsx';

const INITIAL_SCOPES = ['send'];

/**
 * API token create page: a Name field and the Scopes checkboxes (`send` checked by default);
 * once created, the form is replaced by the one-time token panel. The plain token lives only
 * in this component's state — never in storage, the URL or a module variable — so it is gone
 * once the page unmounts (Done or any other navigation).
 *
 * @returns {React.ReactElement} The rendered create page.
 */
export default function ApiTokenNew() {
  const [name, setName] = useState('');
  const [scopes, setScopes] = useState(INITIAL_SCOPES);
  const [fieldErrors, setFieldErrors] = useState({});
  const [submitError, setSubmitError] = useState(null);
  const [submitting, setSubmitting] = useState(false);
  const [createdToken, setCreatedToken] = useState(null);
  const [copyState, setCopyState] = useState(COPY_IDLE);

  const controller = useMemo(
    () => new ApiTokenNewController({
      setScopes, setFieldErrors, setSubmitError, setSubmitting, setCreatedToken, setCopyState,
    }),
    [],
  );

  const onSubmit = (event) => {
    event.preventDefault();
    return controller.handleSubmit({ name, scopes });
  };

  return ApiTokenNewHelper.render(
    {
      name, scopes, fieldErrors, submitError, submitting, createdToken, copyState,
    },
    {
      onSubmit,
      onNameChange: (event) => setName(event.target.value),
      onToggleScope: (scope) => () => controller.toggleScope(scope),
      onCopy: () => controller.handleCopy(createdToken),
    },
  );
}
