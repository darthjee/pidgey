import { useEffect, useMemo, useState } from 'react';
import SmtpConnectionsController from './controllers/SmtpConnectionsController.js';
import SmtpConnectionTestController, {
  CLOSED_TEST_STATE, buildRecipientUpdate,
} from './controllers/SmtpConnectionTestController.js';
import SmtpConnectionsHelper from './helpers/SmtpConnectionsHelper.jsx';

const INITIAL_CONNECTIONS = [];
const INITIAL_LOAD_STATE = { loading: true, loadError: null };

/**
 * Build the mount-time load effect: triggers `controller.load()` once, unconditionally — a
 * logged-out `401` is handled entirely by `ApiClient`'s refresh/login-modal flow. Extracted as
 * a plain function so it can be exercised directly in tests without a React renderer.
 *
 * @param {SmtpConnectionsController} controller - The page's controller.
 * @returns {Function} Effect callback.
 */
export function buildLoadEffect(controller) {
  return () => {
    controller.load();
  };
}

/**
 * SMTP connections page: lists the organization's SMTP connections, with per-row Activate,
 * Send test email and Delete actions. Open to any logged-in, approved user — there is no
 * client-side login or admin check; a `401` is handled by `ApiClient`.
 *
 * @returns {React.ReactElement} The rendered SMTP connections page.
 */
export default function SmtpConnections() {
  const [connections, setConnections] = useState(INITIAL_CONNECTIONS);
  const [loadState, setLoadState] = useState(INITIAL_LOAD_STATE);
  const [listError, setListError] = useState(null);
  const [testState, setTestState] = useState(CLOSED_TEST_STATE);

  const controller = useMemo(
    () => new SmtpConnectionsController({ setConnections, setLoadState, setListError }),
    [],
  );
  const testController = useMemo(() => new SmtpConnectionTestController(setTestState), []);

  useEffect(() => buildLoadEffect(controller)(), [controller]);

  const onSubmitTest = (event) => {
    event.preventDefault();
    return testController.submit(testState);
  };

  return SmtpConnectionsHelper.render(
    {
      connections, ...loadState, listError, test: testState,
    },
    {
      onActivate: (id) => () => controller.handleActivate(id),
      onDelete: (connection) => () => controller.handleDelete(connection),
      onOpenTest: (connection) => () => testController.open(connection),
      onCloseTest: () => testController.close(),
      onRecipientChange: (event) => setTestState(buildRecipientUpdate(event.target.value)),
      onSubmitTest,
    },
  );
}
