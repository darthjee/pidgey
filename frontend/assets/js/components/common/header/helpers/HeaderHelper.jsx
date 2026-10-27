import Navbar from 'react-bootstrap/cjs/Navbar.js';
import Nav from 'react-bootstrap/cjs/Nav.js';
import Container from 'react-bootstrap/cjs/Container.js';
import NavDropdown from 'react-bootstrap/cjs/NavDropdown.js';

/**
 * Render a single link that opens the login modal in a given mode instead of navigating.
 *
 * @param {string} mode - Mode to open the modal in (`'password'`, `'register'`, or
 *   `'recover'`).
 * @param {string} label - Link text.
 * @param {Function} onOpenLogin - Called with `mode` when the link is clicked.
 * @returns {React.ReactElement} The rendered link.
 */
function renderLoginLink(mode, label, onOpenLogin) {
  const handleClick = (event) => {
    event.preventDefault();
    onOpenLogin(mode);
  };

  return <Nav.Link href="#" onClick={handleClick}>{label}</Nav.Link>;
}

/**
 * Render the Admin Users nav link, only shown to a logged-in admin.
 *
 * @param {boolean} isAdmin - Whether the current session belongs to an admin user.
 * @returns {React.ReactElement|null} The Admin Users link, or `null` for a non-admin.
 */
function renderAdminLink(isAdmin) {
  if (!isAdmin) {
    return null;
  }

  return <Nav.Link href="#/admin/users">Admin Users</Nav.Link>;
}

/**
 * Render the "Organization" dropdown, shown to every logged-in user — like
 * {@link renderMyAccountDropdown}, it takes no `isAdmin`-style gate. Holds the Settings, SMTP
 * connections and API tokens items, in that order.
 *
 * @returns {React.ReactElement} The rendered "Organization" dropdown.
 */
function renderOrganizationDropdown() {
  return (
    <NavDropdown title="Organization" id="organization-dropdown" renderMenuOnMount>
      <NavDropdown.Item href="#/organization">Settings</NavDropdown.Item>
      <NavDropdown.Item href="#/smtp-connections">SMTP connections</NavDropdown.Item>
      <NavDropdown.Item href="#/api-tokens">API tokens</NavDropdown.Item>
    </NavDropdown>
  );
}

/**
 * Render the "My account" dropdown, unconditionally shown once logged in — unlike
 * {@link renderAdminLink}, it takes no `isAdmin`-style gate. Holds one item per
 * account page; anticipates further account pages nesting under it later.
 *
 * @returns {React.ReactElement} The rendered "My account" dropdown.
 */
function renderMyAccountDropdown() {
  return (
    <NavDropdown title="My account" id="my-account-dropdown" renderMenuOnMount>
      <NavDropdown.Item href="#/account/authorization-requests">Authorizations</NavDropdown.Item>
      <NavDropdown.Item href="#/account/my-account">Account</NavDropdown.Item>
    </NavDropdown>
  );
}

/**
 * Render the Login/Register/Recover links when logged out, or, when logged in: the Admin Users
 * link (admins only), the "Organization" dropdown, the "My account" dropdown and the Logout
 * action. Renders nothing
 * while the session is pending admin approval (the Awaiting-approval view owns Log out then).
 *
 * @param {boolean} isLoggedIn - Whether a session is currently active.
 * @param {boolean} isAdmin - Whether the current session belongs to an admin user.
 * @param {Function} onLogout - Click handler for the Logout link, used when logged in.
 * @param {Function} onOpenLogin - Called with a mode string (`'password'` / `'register'` /
 *   `'recover'`) to open the login modal, used by the Login/Register/Recover links.
 * @param {boolean} pending - Whether the session is awaiting admin approval.
 * @returns {React.ReactElement|null} The rendered auth nav links, or `null` while pending.
 */
function renderAuthLinks(isLoggedIn, isAdmin, onLogout, onOpenLogin, pending) {
  if (pending) {
    return null;
  }

  if (isLoggedIn) {
    return (
      <>
        {renderAdminLink(isAdmin)}
        {renderOrganizationDropdown()}
        {renderMyAccountDropdown()}
        <Nav.Link href="#" onClick={onLogout}>Logout</Nav.Link>
      </>
    );
  }

  return (
    <>
      {renderLoginLink('password', 'Login', onOpenLogin)}
      {renderLoginLink('register', 'Register', onOpenLogin)}
      {renderLoginLink('recover', 'Recover', onOpenLogin)}
    </>
  );
}

/**
 * Rendering helper for the Header element.
 */
const HeaderHelper = {
  /**
   * Render the application navigation bar. When logged in (and not pending), it shows the
   * Admin Users link (admins only), the "Organization" and "My account" dropdowns and Logout.
   *
   * @param {boolean} isLoggedIn - Whether a session is currently active.
   * @param {boolean} isAdmin - Whether the current session belongs to an admin user.
   * @param {Function} onLogout - Click handler for the Logout link, used when logged in.
   * @param {Function} onOpenLogin - Called with a mode string (`'password'` / `'register'` /
   *   `'recover'`) to open the login modal, used by the Login/Register/Recover links when
   *   logged out.
   * @param {boolean} [pending] - Whether the session is awaiting admin approval; hides every
   *   auth link (the brand stays).
   * @returns {React.ReactElement} The rendered navigation bar.
   */
  render(isLoggedIn, isAdmin, onLogout, onOpenLogin, pending = false) {
    return (
      <Navbar bg="light" expand="md">
        <Container fluid>
          <Navbar.Brand href="#/">pidgey</Navbar.Brand>
          <Navbar.Toggle aria-controls="header-navbar" />
          <Navbar.Collapse id="header-navbar">
            <Nav className="me-auto">
              {renderAuthLinks(isLoggedIn, isAdmin, onLogout, onOpenLogin, pending)}
            </Nav>
          </Navbar.Collapse>
        </Container>
      </Navbar>
    );
  },
};

export default HeaderHelper;
