import { useMemo, useState } from 'react';
import { fetchAuthSession, signIn, signOut } from 'aws-amplify/auth';
import { appConfig, isAmplifyConfigured } from './amplifyConfig';
import './App.css';

const initialTicketDraft = {
  title: 'Network issue in hostel',
  category: 'network/it_team',
  description: 'Wi-Fi is unstable from 9PM to 11PM in Block B.',
  location: 'Hostel Block B - Floor 3',
};

function toPretty(value) {
  if (typeof value === 'string') {
    return value;
  }
  return JSON.stringify(value, null, 2);
}

function App() {
  const [sapId, setSapId] = useState('');
  const [password, setPassword] = useState('');
  const [signedInSapId, setSignedInSapId] = useState('');
  const [idTokenPreview, setIdTokenPreview] = useState('Not signed in');
  const [statusText, setStatusText] = useState('Sign in using SAP ID and password.');
  const [statusTone, setStatusTone] = useState('neutral');
  const [busyAction, setBusyAction] = useState('');
  const [output, setOutput] = useState('API response will appear here.');
  const [ticketDraft, setTicketDraft] = useState(initialTicketDraft);

  const apiConfigured = useMemo(() => Boolean(appConfig.apiBaseUrl), []);

  const isSignedIn = Boolean(signedInSapId);

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
    setIdTokenPreview(`${idToken.slice(0, 24)}...${idToken.slice(-16)}`);
    return idToken;
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
        setSignedInSapId(sapId.trim());
        setNotice('success', 'Sign-in successful. You can now call protected APIs.');
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
      setPassword('');
      setIdTokenPreview('Not signed in');
      setNotice('neutral', 'Signed out.');
    } catch (error) {
      setNotice('error', `Sign-out failed: ${error.message}`);
    } finally {
      setBusyAction('');
    }
  };

  const callApi = async ({ label, path, method = 'GET', body = null }) => {
    if (!apiConfigured) {
      setNotice('error', 'API base URL missing. Check VITE_API_BASE_URL.');
      return;
    }

    if (!isSignedIn) {
      setNotice('error', 'Please sign in first.');
      return;
    }

    setBusyAction(label);
    try {
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
        parsed = raw ? JSON.parse(raw) : { empty: true };
      } catch {
        parsed = raw;
      }

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${toPretty(parsed)}`);
      }

      setOutput(toPretty(parsed));
      setNotice('success', `${label} completed.`);
    } catch (error) {
      setNotice('error', `${label} failed: ${error.message}`);
      setOutput(toPretty({ error: error.message }));
    } finally {
      setBusyAction('');
    }
  };

  const busy = (name) => busyAction === name;

  return (
    <main className="page-shell">
      <header className="hero-header">
        <p className="kicker">CPRTL Cloud Console</p>
        <h1>Compliant Portal Frontend</h1>
        <p className="hero-subtitle">
          SAP ID login with Cognito, secure API access with JWT, and direct calls to your
          Lambda-backed ticket routes.
        </p>
      </header>

      <section className="layout-grid">
        <article className="card auth-card">
          <h2>1. Sign In</h2>
          <p className="card-copy">Use the same SAP ID used as Cognito username.</p>

          <form onSubmit={handleSignIn} className="auth-form">
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

            <div className="button-row">
              <button type="submit" disabled={busyAction !== ''}>
                {busy('signin') ? 'Signing in...' : 'Sign In'}
              </button>
              <button type="button" onClick={handleSignOut} disabled={busyAction !== ''}>
                {busy('signout') ? 'Signing out...' : 'Sign Out'}
              </button>
            </div>
          </form>

          <dl className="meta-grid">
            <dt>Auth Config</dt>
            <dd>{isAmplifyConfigured ? 'Ready' : 'Missing'}</dd>

            <dt>API Config</dt>
            <dd>{apiConfigured ? 'Ready' : 'Missing'}</dd>

            <dt>Signed In User</dt>
            <dd>{signedInSapId || 'None'}</dd>
          </dl>

          <div className={`notice ${statusTone}`}>{statusText}</div>
          <p className="token-preview">Token: {idTokenPreview}</p>
        </article>

        <article className="card actions-card">
          <h2>2. Test API Routes</h2>
          <p className="card-copy">These actions call the API Gateway endpoint configured in Amplify.</p>

          <div className="button-grid">
            <button
              onClick={() => callApi({ label: 'Load My Tickets', path: '/tickets/me' })}
              disabled={busyAction !== ''}
            >
              {busy('Load My Tickets') ? 'Loading...' : 'GET /tickets/me'}
            </button>

            <button
              onClick={() => callApi({ label: 'Load Notifications', path: '/notifications' })}
              disabled={busyAction !== ''}
            >
              {busy('Load Notifications') ? 'Loading...' : 'GET /notifications'}
            </button>

            <button
              onClick={() =>
                callApi({
                  label: 'Create Ticket',
                  path: '/tickets',
                  method: 'POST',
                  body: ticketDraft,
                })
              }
              disabled={busyAction !== ''}
            >
              {busy('Create Ticket') ? 'Submitting...' : 'POST /tickets'}
            </button>
          </div>

          <div className="ticket-form">
            <label>
              Ticket Title
              <input
                value={ticketDraft.title}
                onChange={(event) =>
                  setTicketDraft((prev) => ({ ...prev, title: event.target.value }))
                }
              />
            </label>

            <label>
              Category
              <input
                value={ticketDraft.category}
                onChange={(event) =>
                  setTicketDraft((prev) => ({ ...prev, category: event.target.value }))
                }
              />
            </label>

            <label>
              Location
              <input
                value={ticketDraft.location}
                onChange={(event) =>
                  setTicketDraft((prev) => ({ ...prev, location: event.target.value }))
                }
              />
            </label>

            <label>
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
        </article>

        <article className="card output-card">
          <h2>3. Response Output</h2>
          <p className="card-copy">Inspect successful payloads and errors here.</p>
          <pre>{output}</pre>
        </article>
      </section>
    </main>
  );
}

export default App;
