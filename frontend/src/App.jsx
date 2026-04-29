import { useEffect, useMemo, useState } from 'react';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';

const roleOptions = [
  { value: 'student', label: 'Student' },
  { value: 'lecturer', label: 'Lecturer' },
  { value: 'system_analyst', label: 'System Analyst' },
  { value: 'super_admin', label: 'Super Admin' },
];

async function apiCall(path, method, body) {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    method,
    headers: {
      'Content-Type': 'application/json',
    },
    credentials: 'include',
    body: body ? JSON.stringify(body) : undefined,
  });

  const json = await response.json();
  if (!response.ok) {
    throw new Error(json.message || 'Request failed');
  }

  return json;
}

function SignupForm({ onSuccess }) {
  const [form, setForm] = useState({
    fullName: '',
    email: '',
    password: '',
    requestedRole: 'student',
  });
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');

  const onChange = (event) => {
    setForm((current) => ({
      ...current,
      [event.target.name]: event.target.value,
    }));
  };

  const onSubmit = async (event) => {
    event.preventDefault();
    setLoading(true);
    setMessage('');

    try {
      const result = await apiCall('/auth/signup', 'POST', form);
      setMessage(result.message || 'Signup submitted.');
      onSuccess?.();
    } catch (error) {
      setMessage(error.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form className="card" onSubmit={onSubmit}>
      <h2>Sign Up</h2>
      <p className="helper">Create account for any role. Access starts after approval.</p>
      <input name="fullName" placeholder="Full name" value={form.fullName} onChange={onChange} required />
      <input name="email" type="email" placeholder="Email" value={form.email} onChange={onChange} required />
      <input
        name="password"
        type="password"
        placeholder="Password (min 8 chars)"
        value={form.password}
        onChange={onChange}
        required
      />
      <select name="requestedRole" value={form.requestedRole} onChange={onChange}>
        {roleOptions.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      <button type="submit" disabled={loading}>{loading ? 'Submitting...' : 'Create Account'}</button>
      {message ? <p className="message">{message}</p> : null}
    </form>
  );
}

function SigninForm({ onSignedIn }) {
  const [form, setForm] = useState({ email: '', password: '' });
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');

  const onChange = (event) => {
    setForm((current) => ({
      ...current,
      [event.target.name]: event.target.value,
    }));
  };

  const onSubmit = async (event) => {
    event.preventDefault();
    setLoading(true);
    setMessage('');

    try {
      const result = await apiCall('/auth/signin', 'POST', form);
      onSignedIn(result.user);
    } catch (error) {
      setMessage(error.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form className="card" onSubmit={onSubmit}>
      <h2>Sign In</h2>
      <p className="helper">Use your assigned admin credentials.</p>
      <input name="email" type="email" placeholder="Email" value={form.email} onChange={onChange} required />
      <input name="password" type="password" placeholder="Password" value={form.password} onChange={onChange} required />
      <button type="submit" disabled={loading}>{loading ? 'Signing in...' : 'Sign In'}</button>
      {message ? <p className="message">{message}</p> : null}
    </form>
  );
}

function ApprovalPanel({ user }) {
  const [items, setItems] = useState([]);
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(false);

  const canReview = useMemo(
    () => user.roles.includes('super_admin') || user.roles.includes('system_analyst'),
    [user.roles]
  );

  const loadItems = async () => {
    if (!canReview) {
      return;
    }
    setLoading(true);
    setMessage('');

    try {
      const result = await apiCall('/approvals/pending', 'GET');
      setItems(result.items || []);
    } catch (error) {
      setMessage(error.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadItems();
  }, []);

  const review = async (requestId, action) => {
    try {
      await apiCall(`/approvals/${requestId}/review`, 'POST', { action });
      await loadItems();
    } catch (error) {
      setMessage(error.message);
    }
  };

  if (!canReview) {
    return (
      <div className="card">
        <h3>Approvals</h3>
        <p className="helper">No approval permissions for your current role.</p>
      </div>
    );
  }

  return (
    <div className="card">
      <div className="row between">
        <h3>Pending Approval Requests</h3>
        <button onClick={loadItems} disabled={loading}>{loading ? 'Refreshing...' : 'Refresh'}</button>
      </div>
      {items.length === 0 ? <p className="helper">No pending requests.</p> : null}
      {items.map((item) => (
        <div key={item.id} className="approval-item">
          <div>
            <strong>{item.full_name}</strong>
            <p>{item.email}</p>
            <p>Requested Role: <code>{item.requested_role}</code></p>
          </div>
          <div className="row">
            <button onClick={() => review(item.id, 'approve')}>Approve</button>
            <button className="danger" onClick={() => review(item.id, 'reject')}>Reject</button>
          </div>
        </div>
      ))}
      {message ? <p className="message">{message}</p> : null}
    </div>
  );
}

function Dashboard({ user, onSignOut }) {
  return (
    <main className="container">
      <div className="card">
        <div className="row between">
          <h2>Welcome, {user.fullName}</h2>
          <button onClick={onSignOut}>Sign Out</button>
        </div>
        <p>Email: {user.email}</p>
        <p>Status: <code>{user.status}</code></p>
        <p>Roles: {user.roles.length ? user.roles.join(', ') : 'No active roles'}</p>
      </div>
      <ApprovalPanel user={user} />
    </main>
  );
}

export default function App() {
  const [user, setUser] = useState(null);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    const bootstrap = async () => {
      try {
        const result = await apiCall('/auth/me', 'GET');
        setUser(result.user);
      } catch (error) {
        setUser(null);
      } finally {
        setReady(true);
      }
    };

    bootstrap();
  }, []);

  const onSignedIn = (signedInUser) => {
    setUser(signedInUser);
  };

  const onSignOut = () => {
    apiCall('/auth/logout', 'POST').catch(() => undefined);
    setUser(null);
  };

  if (!ready) {
    return <main className="container"><p>Loading...</p></main>;
  }

  if (user) {
    return <Dashboard user={user} onSignOut={onSignOut} />;
  }

  return (
    <main className="container auth-grid">
      <SignupForm />
      <SigninForm onSignedIn={onSignedIn} />
    </main>
  );
}
