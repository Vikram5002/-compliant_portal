import { useMemo, useState } from 'react';
import { fetchAuthSession, signIn, signOut } from 'aws-amplify/auth';
import { appConfig, isAmplifyConfigured } from './amplifyConfig';
import './App.css';

const initialTicketDraft = {
  title: 'Network issue in hostel',
  category: 'network/it_team',
  description: 'Wi-Fi is unstable from 9PM to 11PM in Block B.',
  location: 'Hostel Block B - Floor 3',
  assignedTo: '',
};

const fallbackSapRoleMap = {
  '00000': 'security',
  '70572300022': 'student',
  '0123456': 'admin',
  '70572300015': 'house_keeping',
  '7057230003': 'warden',
  '7057230004': 'staff',
  '000011': 'network/it_team',
  '7057230007': 'rector',
  '09876543211': 'electrition',
  '70572300000': 'super_visor',
  '8057230001': 'student',
  '8057230002': 'student',
  '90972300021': 'sub_security',
  '456789': 'driver',
  '90972300015': 'sub_house_keeping',
  admin: 'admin',
};

const knownRoles = new Set([
  'student',
  'admin',
  'super_visor',
  'staff',
  'maintenance',
  'warden',
  'rector',
  'security',
  'house_keeping',
  'network/it_team',
  'electrition',
  'driver',
  'sub_staff',
  'sub_security',
  'sub_maintenance',
  'sub_house_keeping',
  'sub_warden',
  'sub_rector',
]);

const staffRoles = [
  'staff',
  'maintenance',
  'warden',
  'rector',
  'security',
  'house_keeping',
  'network/it_team',
];

const staffWithSubRoles = ['staff', 'maintenance', 'warden', 'security', 'house_keeping'];

const logoFallback =
  'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgdmlld0JveD0iMCAwIDEyMCAxMjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMjAiIGhlaWdodD0iMTIwIiBmaWxsPSIjQzQxRTNBIi8+Cjx0ZXh0IHg9IjYwIiB5PSI2NSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0id2hpdGUiIGZvbnQtc2l6ZT0iMTgiPk5NSU1TPC90ZXh0Pgo8L3N2Zz4=';

function toPretty(value) {
  if (typeof value === 'string') {
    return value;
  }
  return JSON.stringify(value, null, 2);
}

function normalizeArrayPayload(payload, preferredKey) {
  if (Array.isArray(payload)) {
    return payload;
  }

  if (payload && typeof payload === 'object') {
    if (preferredKey && Array.isArray(payload[preferredKey])) {
      return payload[preferredKey];
    }
    if (Array.isArray(payload.items)) {
      return payload.items;
    }
    if (Array.isArray(payload.data)) {
      return payload.data;
    }
    if (Array.isArray(payload.records)) {
      return payload.records;
    }
  }

  return [];
}

function mapTicket(raw) {
  return {
    ticketId: String(raw.ticket_id ?? raw.ticketId ?? raw.id ?? ''),
    title: raw.title ?? raw.subject ?? 'Untitled',
    category: raw.category ?? 'General',
    status: raw.status ?? 'Received',
    priority: raw.priority ?? 'medium',
    createdAt: raw.created_at ?? raw.createdAt ?? null,
    creatorSap:
      raw.creator_sap_id ?? raw.creatorSapId ?? raw.creator_sap ?? raw.created_by ?? raw.userSapId ?? 'N/A',
    assignedTo: raw.assigned_to ?? raw.assignedTo ?? '',
    reassignedTo: raw.reassigned_to ?? raw.reassignedTo ?? '',
    subStaffStatus: raw.sub_staff_status ?? raw.subStaffStatus ?? null,
    feedbackText: raw.feedback_text ?? raw.feedbackText ?? '',
    rejectionReason: raw.parent_notes ?? raw.rejection_reason ?? '',
    raw,
  };
}

function mapNotification(raw) {
  return {
    notificationId: String(raw.notification_id ?? raw.notificationId ?? raw.id ?? ''),
    message: raw.message ?? raw.text ?? 'No message',
    ticketId: raw.ticket_id ?? raw.ticketId ?? null,
    isRead: Boolean(raw.is_read ?? raw.isRead ?? false),
    createdAt: raw.created_at ?? raw.createdAt ?? null,
    raw,
  };
}

function decodeJwtPayload(token) {
  if (!token || token.split('.').length < 2) {
    return null;
  }

  try {
    const base64 = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/');
    const json = decodeURIComponent(
      atob(base64)
        .split('')
        .map((char) => `%${char.charCodeAt(0).toString(16).padStart(2, '0')}`)
        .join(''),
    );
    return JSON.parse(json);
  } catch {
    return null;
  }
}

function normalizeRole(value) {
  if (!value || typeof value !== 'string') {
    return '';
  }

  let normalized = value.trim().toLowerCase();
  normalized = normalized.replace(/^role[:_ -]*/g, '');
  normalized = normalized.replace(/^roles[:_ -]*/g, '');
  normalized = normalized.replace(/\s+/g, '_');

  const aliases = {
    supervisor: 'super_visor',
    housekeeping: 'house_keeping',
    sub_housekeeping: 'sub_house_keeping',
    network_it_team: 'network/it_team',
  };

  return aliases[normalized] ?? normalized;
}

function isKnownRole(role) {
  if (!role) {
    return false;
  }
  return knownRoles.has(role);
}

function resolveRoleFromIdentity(payload, fallbackUsername) {
  const candidateRoles = [
    payload?.['custom:role'],
    payload?.role,
    payload?.user_role,
    payload?.['custom:user_role'],
  ];

  const groups = payload?.['cognito:groups'];
  if (Array.isArray(groups)) {
    candidateRoles.push(...groups);
  }

  for (const candidate of candidateRoles) {
    const normalized = normalizeRole(candidate);
    if (isKnownRole(normalized)) {
      return { role: normalized, source: 'token' };
    }
  }

  const fallbackRole = fallbackSapRoleMap[fallbackUsername] ?? 'student';
  return { role: fallbackRole, source: 'fallback-map' };
}

function extractRoleFromApiResponse(payload) {
  if (!payload || typeof payload !== 'object') {
    return '';
  }

  const candidates = [
    payload.role,
    payload.userRole,
    payload.user?.role,
    payload.profile?.role,
    payload.meta?.role,
  ];

  for (const candidate of candidates) {
    const normalized = normalizeRole(candidate);
    if (isKnownRole(normalized)) {
      return normalized;
    }
  }

  return '';
}

function humanizeRole(role) {
  return role
    .replace('network/it_team', 'Network / IT Team')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatDate(value) {
  if (!value) {
    return 'N/A';
  }

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return String(value);
  }

  return parsed.toLocaleString();
}

function statusClass(status) {
  return String(status ?? 'received')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '');
}

function buildNavItems(role, unreadCount) {
  const nav = [
    { key: 'dashboard', icon: 'fas fa-tachometer-alt', label: 'Dashboard' },
    { key: 'my-own-tickets', icon: 'fas fa-ticket-alt', label: 'My Own Tickets' },
  ];

  if (role.startsWith('sub_')) {
    nav.push({ key: 'my-tasks', icon: 'fas fa-tasks', label: 'My Tasks' });
  } else if (staffWithSubRoles.includes(role)) {
    nav.push({ key: 'assigned-tickets-sub', icon: 'fas fa-tasks', label: 'Assigned Tickets' });
  } else if (staffRoles.includes(role)) {
    nav.push({ key: 'assigned-tickets', icon: 'fas fa-tasks', label: 'Assigned Tickets' });
  }

  if (['admin', 'super_visor'].includes(role)) {
    nav.push({ key: 'admin-dashboard', icon: 'fas fa-user-shield', label: 'Admin Dashboard' });
  }

  if (role === 'admin') {
    nav.push({ key: 'add-users', icon: 'fas fa-user-plus', label: 'Add Users' });
  }

  nav.push({ key: 'notifications', icon: 'fas fa-bell', label: 'Notifications', badge: unreadCount });
  nav.push({ key: 'logout', icon: 'fas fa-sign-out-alt', label: 'Logout' });

  return nav;
}

function getPageTitle(view, role) {
  if (view === 'dashboard') {
    return 'Dashboard';
  }
  if (view === 'my-own-tickets') {
    return 'My Own Tickets';
  }
  if (view === 'admin-dashboard') {
    return 'Admin Dashboard';
  }
  if (view === 'my-tasks') {
    return 'My Assigned Tasks';
  }
  if (view === 'assigned-tickets-sub') {
    return 'My Tickets & Assignments';
  }
  if (view === 'assigned-tickets') {
    return 'Assigned Tickets';
  }
  if (view === 'notifications') {
    return 'Notifications';
  }
  if (view === 'add-users') {
    return 'Add Users';
  }
  if (view === 'closed-tickets') {
    return 'Closed Tickets';
  }
  if (view === 'ticket-details') {
    return 'Ticket Details';
  }
  return humanizeRole(role);
}

function App() {
  const [sapId, setSapId] = useState('');
  const [password, setPassword] = useState('');
  const [signedInSapId, setSignedInSapId] = useState('');
  const [userRole, setUserRole] = useState('student');
  const [roleSource, setRoleSource] = useState('fallback-map');
  const [activeView, setActiveView] = useState('dashboard');
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [selectedTicketId, setSelectedTicketId] = useState('');
  const [idTokenPreview, setIdTokenPreview] = useState('Not signed in');
  const [statusText, setStatusText] = useState('Sign in using SAP ID and password.');
  const [statusTone, setStatusTone] = useState('neutral');
  const [busyAction, setBusyAction] = useState('');
  const [output, setOutput] = useState('No API action triggered yet.');
  const [ticketDraft, setTicketDraft] = useState(initialTicketDraft);
  const [tickets, setTickets] = useState([]);
  const [notifications, setNotifications] = useState([]);
  const [statusDrafts, setStatusDrafts] = useState({});
  const [assignDrafts, setAssignDrafts] = useState({});
  const [approvalNotes, setApprovalNotes] = useState({});

  const apiConfigured = useMemo(() => Boolean(appConfig.apiBaseUrl), []);
  const unreadNotifications = useMemo(
    () => notifications.filter((item) => !item.isRead).length,
    [notifications],
  );
  const navItems = useMemo(
    () => buildNavItems(userRole, unreadNotifications),
    [userRole, unreadNotifications],
  );

  const isSignedIn = Boolean(signedInSapId);
  const visibleTickets = useMemo(
    () => tickets.filter((ticket) => String(ticket.status).toLowerCase() !== 'closed'),
    [tickets],
  );
  const closedTickets = useMemo(
    () => tickets.filter((ticket) => String(ticket.status).toLowerCase() === 'closed'),
    [tickets],
  );
  const selectedTicket = useMemo(
    () => tickets.find((ticket) => ticket.ticketId === selectedTicketId) ?? null,
    [tickets, selectedTicketId],
  );
  const parentApprovalCandidates = useMemo(
    () =>
      tickets.filter(
        (ticket) =>
          ticket.subStaffStatus === 'pending_approval' ||
          String(ticket.status).toLowerCase() === 'solution proposed',
      ),
    [tickets],
  );

  const setNotice = (tone, text) => {
    setStatusTone(tone);
    setStatusText(text);
  };

  const refreshSessionPreview = async () => {
    const session = await fetchAuthSession();
    const idToken = session.tokens?.idToken?.toString();
    if (!idToken) {
      throw new Error('Cognito sign-in succeeded, but ID token is missing.');
    }

    const payload = decodeJwtPayload(idToken) ?? {};
    const username =
      payload['cognito:username'] ?? payload.username ?? signedInSapId ?? sapId.trim() ?? '';
    const identity = resolveRoleFromIdentity(payload, username);

    setSignedInSapId(username);
    setUserRole(identity.role);
    setRoleSource(identity.source);
    setIdTokenPreview(`${idToken.slice(0, 24)}...${idToken.slice(-16)}`);

    return idToken;
  };

  const callApi = async ({ label, path, method = 'GET', body = null, silent = false }) => {
    if (!apiConfigured) {
      throw new Error('API base URL missing. Check VITE_API_BASE_URL.');
    }

    if (!isSignedIn) {
      throw new Error('Please sign in first.');
    }

    const idToken = await refreshSessionPreview();
    const response = await fetch(`${appConfig.apiBaseUrl}${path}`, {
      method,
      headers: {
        Authorization: `Bearer ${idToken}`,
        'Content-Type': 'application/json',
      },
      body: body ? JSON.stringify(body) : undefined,
    });

    const raw = await response.text();
    let parsed;
    try {
      parsed = raw ? JSON.parse(raw) : {};
    } catch {
      parsed = raw;
    }

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${toPretty(parsed)}`);
    }

    const apiRole = extractRoleFromApiResponse(parsed);
    if (apiRole) {
      setUserRole(apiRole);
      setRoleSource('api');
    }

    if (!silent) {
      setOutput(`${label}\n\n${toPretty(parsed)}`);
    }

    return parsed;
  };

  const loadMyTickets = async () => {
    const data = await callApi({
      label: 'GET /tickets/me',
      path: '/tickets/me',
      silent: true,
    });
    const normalized = normalizeArrayPayload(data, 'tickets').map(mapTicket);
    setTickets(normalized);
    return normalized;
  };

  const loadNotifications = async () => {
    const data = await callApi({
      label: 'GET /notifications',
      path: '/notifications',
      silent: true,
    });
    const normalized = normalizeArrayPayload(data, 'notifications').map(mapNotification);
    setNotifications(normalized);
    return normalized;
  };

  const loadInitialData = async () => {
    const errors = [];

    try {
      await loadMyTickets();
    } catch (error) {
      errors.push(`tickets: ${error.message}`);
    }

    try {
      await loadNotifications();
    } catch (error) {
      errors.push(`notifications: ${error.message}`);
    }

    if (errors.length > 0) {
      throw new Error(errors.join(' | '));
    }
  };

  const createTicket = async () => {
    const payload = {
      title: ticketDraft.title,
      category: ticketDraft.category,
      location: ticketDraft.location,
      description: ticketDraft.description,
      assignedTo: ticketDraft.assignedTo || undefined,
    };

    await callApi({ label: 'POST /tickets', path: '/tickets', method: 'POST', body: payload });
    await loadMyTickets();
    setTicketDraft(initialTicketDraft);
  };

  const updateTicketStatus = async (ticketId) => {
    const status = statusDrafts[ticketId];
    if (!status) {
      throw new Error('Select a status first.');
    }

    await callApi({
      label: `PATCH /tickets/${ticketId}/status`,
      path: `/tickets/${ticketId}/status`,
      method: 'PATCH',
      body: { status },
    });
    await loadMyTickets();
  };

  const requestApproval = async (ticketId) => {
    await callApi({
      label: `POST /tickets/${ticketId}/approvals/request`,
      path: `/tickets/${ticketId}/approvals/request`,
      method: 'POST',
      body: { approvalNotes: approvalNotes[ticketId] ?? '' },
    });
    await loadMyTickets();
  };

  const decideApproval = async (ticketId, action) => {
    await callApi({
      label: `POST /tickets/${ticketId}/approvals/decision`,
      path: `/tickets/${ticketId}/approvals/decision`,
      method: 'POST',
      body: {
        action,
        parentNotes: approvalNotes[ticketId] ?? '',
      },
    });
    await loadMyTickets();
  };

  const assignTicket = async (ticketId) => {
    const assignedTo = assignDrafts[ticketId];
    if (!assignedTo) {
      throw new Error('Enter a staff SAP ID or user ID to assign.');
    }

    await callApi({
      label: `PATCH /tickets/${ticketId}/assign`,
      path: `/tickets/${ticketId}/assign`,
      method: 'PATCH',
      body: { assignedTo },
    });
    await loadMyTickets();
  };

  const runAction = async (label, fn) => {
    setBusyAction(label);
    try {
      await fn();
      setNotice('success', `${label} completed.`);
    } catch (error) {
      setNotice('error', `${label} failed: ${error.message}`);
      setOutput(toPretty({ error: error.message }));
    } finally {
      setBusyAction('');
    }
  };

  const handleSignIn = async (event) => {
    event.preventDefault();

    if (!isAmplifyConfigured) {
      setNotice('error', 'Amplify auth config missing. Check VITE_COGNITO_* values.');
      return;
    }

    if (!sapId.trim() || !password.trim()) {
      setNotice('error', 'Enter both SAP ID and password.');
      return;
    }

    setBusyAction('signin');
    try {
      await signOut();
    } catch {
      // Ignore if there is no active session.
    }

    try {
      const result = await signIn({ username: sapId.trim(), password });
      if (result.nextStep?.signInStep && result.nextStep.signInStep !== 'DONE') {
        setNotice('warn', `Next step required: ${result.nextStep.signInStep}`);
      } else {
        await refreshSessionPreview();

        try {
          await loadInitialData();
          setNotice('success', 'Sign-in successful. Dashboard loaded.');
        } catch (error) {
          setNotice(
            'warn',
            `Sign-in succeeded, but some dashboard data could not load: ${error.message}`,
          );
        }
      }
    } catch (error) {
      setNotice('error', `Sign-in failed: ${error.message}`);
      setSignedInSapId('');
      setIdTokenPreview('Not signed in');
    } finally {
      setBusyAction('');
    }
  };

  const handleSignOut = async () => {
    setBusyAction('signout');
    try {
      await signOut();
      setSignedInSapId('');
      setUserRole('student');
      setRoleSource('fallback-map');
      setPassword('');
      setIdTokenPreview('Not signed in');
      setTickets([]);
      setNotifications([]);
      setSelectedTicketId('');
      setActiveView('dashboard');
      setNotice('neutral', 'Signed out.');
    } catch (error) {
      setNotice('error', `Sign-out failed: ${error.message}`);
    } finally {
      setBusyAction('');
    }
  };

  const handleNavClick = (itemKey) => {
    setMobileMenuOpen(false);
    if (itemKey === 'logout') {
      handleSignOut();
      return;
    }
    setActiveView(itemKey);
  };

  const renderTicketActionCell = (ticket, mode) => (
    <div className="inline-actions">
      {(mode === 'admin-dashboard' || mode === 'assigned-tickets-sub') && (
        <>
          <input
            className="mini-input"
            placeholder="Assign to"
            value={assignDrafts[ticket.ticketId] ?? ''}
            onChange={(event) =>
              setAssignDrafts((prev) => ({ ...prev, [ticket.ticketId]: event.target.value }))
            }
          />
          <button
            type="button"
            className="btn-mini"
            disabled={busyAction !== ''}
            onClick={() => runAction(`Assign #${ticket.ticketId}`, () => assignTicket(ticket.ticketId))}
          >
            Assign
          </button>
        </>
      )}

      {(mode === 'assigned-tickets' || mode === 'assigned-tickets-sub' || mode === 'my-tasks') && (
        <>
          <select
            className="mini-select"
            value={statusDrafts[ticket.ticketId] ?? ''}
            onChange={(event) =>
              setStatusDrafts((prev) => ({ ...prev, [ticket.ticketId]: event.target.value }))
            }
          >
            <option value="">Update Status</option>
            <option value="Received">Received</option>
            <option value="In Progress">In Progress</option>
            <option value="Solution Proposed">Solution Proposed</option>
            <option value="Resolved">Resolved</option>
          </select>
          <button
            type="button"
            className="btn-mini"
            disabled={busyAction !== ''}
            onClick={() => runAction(`Update #${ticket.ticketId}`, () => updateTicketStatus(ticket.ticketId))}
          >
            Save
          </button>
        </>
      )}

      {mode === 'my-tasks' && (
        <button
          type="button"
          className="btn-mini btn-approve"
          disabled={busyAction !== ''}
          onClick={() =>
            runAction(`Request approval #${ticket.ticketId}`, () => requestApproval(ticket.ticketId))
          }
        >
          Request Approval
        </button>
      )}

      <button
        type="button"
        className="btn-mini btn-details"
        onClick={() => {
          setSelectedTicketId(ticket.ticketId);
          setActiveView('ticket-details');
        }}
      >
        View Details
      </button>
    </div>
  );

  const renderTicketTable = (rows, mode, emptyText) => {
    if (rows.length === 0) {
      return (
        <tr>
          <td colSpan={7} className="empty-row">
            {emptyText}
          </td>
        </tr>
      );
    }

    return rows.map((ticket) => (
      <tr key={`${mode}-${ticket.ticketId}`}>
        <td>#{ticket.ticketId}</td>
        <td>
          <strong>{ticket.title}</strong>
          <div className="sub-line">{ticket.category}</div>
        </td>
        <td>
          <span className={`status-chip status-${statusClass(ticket.subStaffStatus || ticket.status)}`}>
            {ticket.subStaffStatus === 'pending_approval' ? 'Pending Approval' : ticket.status}
          </span>
        </td>
        <td>{ticket.creatorSap}</td>
        <td>{formatDate(ticket.createdAt)}</td>
        <td>{ticket.priority || 'medium'}</td>
        <td>{renderTicketActionCell(ticket, mode)}</td>
      </tr>
    ));
  };

  const renderDashboardHome = () => (
    <>
      <section className="ticket-form-container">
        <h2>Create New Ticket</h2>
        <div className="form-grid">
          <label>
            Issue Title
            <input
              type="text"
              value={ticketDraft.title}
              onChange={(event) => setTicketDraft((prev) => ({ ...prev, title: event.target.value }))}
              placeholder="e.g., Wi-Fi not working in library"
            />
          </label>
          <label>
            Category
            <input
              type="text"
              value={ticketDraft.category}
              onChange={(event) =>
                setTicketDraft((prev) => ({ ...prev, category: event.target.value }))
              }
              placeholder="network/it_team"
            />
          </label>
          <label>
            Location
            <input
              type="text"
              value={ticketDraft.location}
              onChange={(event) => setTicketDraft((prev) => ({ ...prev, location: event.target.value }))}
              placeholder="ACD block, 2nd floor"
            />
          </label>
          <label>
            Assign To (Optional)
            <input
              type="text"
              value={ticketDraft.assignedTo}
              onChange={(event) =>
                setTicketDraft((prev) => ({ ...prev, assignedTo: event.target.value }))
              }
              placeholder="staff SAP ID"
            />
          </label>
          <label className="full-width">
            Description
            <textarea
              rows={4}
              value={ticketDraft.description}
              onChange={(event) =>
                setTicketDraft((prev) => ({ ...prev, description: event.target.value }))
              }
            />
          </label>
        </div>
        <div className="form-actions">
          <button
            type="button"
            className="btn"
            disabled={busyAction !== ''}
            onClick={() => runAction('Create ticket', createTicket)}
          >
            Submit Ticket
          </button>
          <button
            type="button"
            className="btn btn-secondary"
            disabled={busyAction !== ''}
            onClick={() => runAction('Refresh tickets', loadMyTickets)}
          >
            Refresh Tickets
          </button>
        </div>
      </section>

      <section className="tickets-container">
        <div className="table-title">Recent Active Tickets</div>
        <table className="ticket-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Title</th>
              <th>Status</th>
              <th>Created By</th>
              <th>Created At</th>
              <th>Priority</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>{renderTicketTable(visibleTickets, 'dashboard', 'No tickets found yet.')}</tbody>
        </table>
      </section>
    </>
  );

  const renderAdminDashboard = () => (
    <section className="tickets-container">
      <div className="table-title">Admin Dashboard - Active Tickets</div>
      <table className="ticket-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Status</th>
            <th>Created By</th>
            <th>Created At</th>
            <th>Priority</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>{renderTicketTable(visibleTickets, 'admin-dashboard', 'No active tickets found.')}</tbody>
      </table>
    </section>
  );

  const renderStaffAssigned = (title, modeKey) => (
    <section className="tickets-container">
      <div className="table-title">{title}</div>
      <table className="ticket-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Status</th>
            <th>Created By</th>
            <th>Created At</th>
            <th>Priority</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>{renderTicketTable(visibleTickets, modeKey, 'No tickets found for this view.')}</tbody>
      </table>
    </section>
  );

  const renderApprovalsSection = () => {
    if (parentApprovalCandidates.length === 0) {
      return null;
    }

    return (
      <section className="section approvals-section">
        <div className="section-title">Pending Approvals ({parentApprovalCandidates.length})</div>
        <div className="section-content">
          {parentApprovalCandidates.map((ticket) => (
            <article className="approval-card" key={`approval-${ticket.ticketId}`}>
              <h4>
                Ticket #{ticket.ticketId} - {ticket.title}
              </h4>
              <p>Submitted for approval or marked as Solution Proposed.</p>
              <textarea
                rows={3}
                placeholder="Parent notes (optional for approve, useful for reject)..."
                value={approvalNotes[ticket.ticketId] ?? ''}
                onChange={(event) =>
                  setApprovalNotes((prev) => ({ ...prev, [ticket.ticketId]: event.target.value }))
                }
              />
              <div className="approval-actions">
                <button
                  type="button"
                  className="btn-approve"
                  disabled={busyAction !== ''}
                  onClick={() =>
                    runAction(`Approve #${ticket.ticketId}`, () => decideApproval(ticket.ticketId, 'approve'))
                  }
                >
                  Approve
                </button>
                <button
                  type="button"
                  className="btn-reject"
                  disabled={busyAction !== ''}
                  onClick={() =>
                    runAction(`Reject #${ticket.ticketId}`, () => decideApproval(ticket.ticketId, 'reject'))
                  }
                >
                  Reject
                </button>
              </div>
            </article>
          ))}
        </div>
      </section>
    );
  };

  const renderNotifications = () => (
    <section className="section">
      <div className="section-title">Notifications</div>
      <div className="section-content">
        <div className="form-actions compact-actions">
          <button
            type="button"
            className="btn"
            disabled={busyAction !== ''}
            onClick={() => runAction('Refresh notifications', loadNotifications)}
          >
            Refresh Notifications
          </button>
        </div>
        {notifications.length === 0 ? (
          <div className="no-items">No notifications found.</div>
        ) : (
          <div className="notification-list">
            {notifications.map((item) => (
              <article
                className={`notification-item ${item.isRead ? '' : 'unread'}`}
                key={item.notificationId}
              >
                <div className="notification-message">{item.message}</div>
                <div className="notification-meta">
                  {item.ticketId ? `Ticket #${item.ticketId}` : 'General'} • {formatDate(item.createdAt)}
                </div>
              </article>
            ))}
          </div>
        )}
      </div>
    </section>
  );

  const renderAddUsers = () => (
    <section className="section">
      <div className="section-title">Add Users</div>
      <div className="section-content">
        <p>
          This screen now matches the PHP navigation flow and is ready for backend integration.
          We can connect it next to a bulk import endpoint and single-user creation endpoint.
        </p>
      </div>
    </section>
  );

  const renderClosedTickets = () => (
    <section className="tickets-container">
      <div className="table-title">Closed Tickets</div>
      <table className="ticket-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Status</th>
            <th>Created By</th>
            <th>Created At</th>
            <th>Priority</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>{renderTicketTable(closedTickets, 'closed-tickets', 'No closed tickets found.')}</tbody>
      </table>
    </section>
  );

  const renderTicketDetails = () => {
    if (!selectedTicket) {
      return (
        <section className="section">
          <div className="section-title">Ticket Details</div>
          <div className="section-content">
            <p>No ticket selected. Open a ticket from any dashboard table to view details.</p>
          </div>
        </section>
      );
    }

    return (
      <section className="section">
        <div className="section-title">Ticket #{selectedTicket.ticketId}</div>
        <div className="section-content details-grid">
          <div className="detail-row">
            <span className="detail-label">Title</span>
            <span className="detail-value">{selectedTicket.title}</span>
          </div>
          <div className="detail-row">
            <span className="detail-label">Category</span>
            <span className="detail-value">{selectedTicket.category}</span>
          </div>
          <div className="detail-row">
            <span className="detail-label">Status</span>
            <span className="detail-value">{selectedTicket.status}</span>
          </div>
          <div className="detail-row">
            <span className="detail-label">Priority</span>
            <span className="detail-value">{selectedTicket.priority || 'medium'}</span>
          </div>
          <div className="detail-row">
            <span className="detail-label">Created By</span>
            <span className="detail-value">{selectedTicket.creatorSap}</span>
          </div>
          <div className="detail-row">
            <span className="detail-label">Created At</span>
            <span className="detail-value">{formatDate(selectedTicket.createdAt)}</span>
          </div>
          <div className="detail-row">
            <span className="detail-label">Assigned To</span>
            <span className="detail-value">{selectedTicket.assignedTo || 'Not assigned'}</span>
          </div>
          <div className="detail-row">
            <span className="detail-label">Reassigned To</span>
            <span className="detail-value">{selectedTicket.reassignedTo || 'Not reassigned'}</span>
          </div>
          <div className="detail-row">
            <span className="detail-label">Feedback</span>
            <span className="detail-value">{selectedTicket.feedbackText || 'No feedback yet'}</span>
          </div>
          <div className="detail-row">
            <span className="detail-label">Rejection Reason</span>
            <span className="detail-value">{selectedTicket.rejectionReason || 'None'}</span>
          </div>
        </div>
      </section>
    );
  };

  const renderMainView = () => {
    if (activeView === 'dashboard') {
      return renderDashboardHome();
    }
    if (activeView === 'my-own-tickets') {
      return renderStaffAssigned('My Own Tickets', 'my-own-tickets');
    }
    if (activeView === 'admin-dashboard') {
      return renderAdminDashboard();
    }
    if (activeView === 'assigned-tickets-sub') {
      return (
        <>
          {renderApprovalsSection()}
          {renderStaffAssigned('My Tickets & Assignments', 'assigned-tickets-sub')}
        </>
      );
    }
    if (activeView === 'assigned-tickets') {
      return renderStaffAssigned('Assigned Tickets', 'assigned-tickets');
    }
    if (activeView === 'my-tasks') {
      return renderStaffAssigned('My Assigned Tasks', 'my-tasks');
    }
    if (activeView === 'notifications') {
      return renderNotifications();
    }
    if (activeView === 'add-users') {
      return renderAddUsers();
    }
    if (activeView === 'closed-tickets') {
      return renderClosedTickets();
    }
    if (activeView === 'ticket-details') {
      return renderTicketDetails();
    }
    return renderDashboardHome();
  };

  if (!isSignedIn) {
    return (
      <main className="auth-screen">
        <section className="auth-card">
          <div className="logo-row">
            <img src={logoFallback} alt="NMIMS Logo" />
            <div>
              <h1>NMIMS Issue Tracker</h1>
              <p>Sign in with SAP ID to open your dashboard.</p>
            </div>
          </div>

          <form onSubmit={handleSignIn} className="auth-form-panel">
            <label>
              SAP ID
              <input
                value={sapId}
                onChange={(event) => setSapId(event.target.value)}
                placeholder="70572300022"
                autoComplete="username"
              />
            </label>

            <label>
              Password
              <input
                type="password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                placeholder="Enter password"
                autoComplete="current-password"
              />
            </label>

            <button type="submit" className="btn" disabled={busyAction !== ''}>
              {busyAction === 'signin' ? 'Signing in...' : 'Sign In'}
            </button>
          </form>

          <dl className="meta-grid">
            <dt>Auth Config</dt>
            <dd>{isAmplifyConfigured ? 'Ready' : 'Missing'}</dd>
            <dt>API Config</dt>
            <dd>{apiConfigured ? 'Ready' : 'Missing'}</dd>
          </dl>

          <div className={`message ${statusTone}`}>{statusText}</div>
        </section>
      </main>
    );
  }

  return (
    <div className="app-shell">
      <button
        id="mobile-menu-toggle"
        className={`mobile-menu-toggle ${mobileMenuOpen ? 'active' : ''}`}
        onClick={() => setMobileMenuOpen((prev) => !prev)}
        aria-label="Toggle navigation menu"
      >
        <i className="fas fa-bars" />
      </button>

      <aside className={`sidebar ${mobileMenuOpen ? 'mobile-open' : ''}`}>
        <div className="logo-container">
          <img src={logoFallback} alt="NMIMS Logo" />
          <h3>NMIMS Issue Tracker</h3>
        </div>

        <div className="user-profile">
          <div className="profile-pic">
            <i className="fas fa-user-circle" />
          </div>
          <h4>{humanizeRole(userRole)}</h4>
          <p>{signedInSapId}</p>
        </div>

        {userRole.startsWith('sub_') && (
          <div className="parent-info">
            <strong>Reports To:</strong>
            <br />
            Parent Staff Dashboard
          </div>
        )}

        <nav className="nav-menu" aria-label="Main navigation">
          {navItems.map((item) => (
            <button
              key={item.key}
              type="button"
              className={`nav-item ${activeView === item.key ? 'active' : ''}`}
              onClick={() => handleNavClick(item.key)}
            >
              <i className={item.icon} />
              <span>{item.label}</span>
              {item.badge > 0 && <span className="notif-badge">{item.badge}</span>}
            </button>
          ))}
        </nav>
      </aside>

      <main className="main-content" onClick={() => mobileMenuOpen && setMobileMenuOpen(false)}>
        <header className="page-header">
          <div>
            <h1>{getPageTitle(activeView, userRole)}</h1>
            <p className="header-subtitle">API endpoint: {appConfig.apiBaseUrl || 'Not configured'}</p>
            <p className="header-subtitle">
              Role: {humanizeRole(userRole)} (source: {roleSource})
            </p>
          </div>

          <div className="header-actions">
            <button
              type="button"
              className="btn"
              disabled={busyAction !== ''}
              onClick={() => runAction('Load tickets', loadMyTickets)}
            >
              Refresh Tickets
            </button>
            <button
              type="button"
              className="btn btn-secondary"
              disabled={busyAction !== ''}
              onClick={() => runAction('Load notifications', loadNotifications)}
            >
              Refresh Notifications
            </button>

            {activeView !== 'closed-tickets' && (
              <button
                type="button"
                className="btn btn-tertiary"
                onClick={() => setActiveView('closed-tickets')}
              >
                Closed Tickets ({closedTickets.length})
              </button>
            )}

            {(activeView === 'closed-tickets' || activeView === 'ticket-details') && (
              <button
                type="button"
                className="btn btn-tertiary"
                onClick={() => setActiveView('dashboard')}
              >
                Back to Dashboard
              </button>
            )}
          </div>
        </header>

        <div className={`message ${statusTone}`}>{statusText}</div>
        {renderMainView()}

        <section className="section">
          <div className="section-title">Response Output</div>
          <div className="section-content">
            <div className="token-line">Session Token Preview: {idTokenPreview}</div>
            <pre className="output-panel">{output}</pre>
            <div className="status-footer">
              Signed in as <strong>{signedInSapId}</strong> ({humanizeRole(userRole)}) • Unread notifications:{' '}
              <strong>{unreadNotifications}</strong>
            </div>
          </div>
        </section>
      </main>
    </div>
  );
}

export default App;

