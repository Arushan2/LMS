import { useEffect, useMemo, useState } from 'react';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';

const roleOptions = [
  { value: 'student', label: 'Student', icon: '👨‍🎓' },
  { value: 'lecturer', label: 'Lecturer', icon: '👨‍🏫' },
  { value: 'system_analyst', label: 'System Analyst', icon: '👨‍💼' },
  { value: 'super_admin', label: 'Super Admin', icon: '👑' },
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
  const [errors, setErrors] = useState({});
  const [hasSuperAdmin, setHasSuperAdmin] = useState(false);
  const [rolesLoading, setRolesLoading] = useState(true);
  const [showPassword, setShowPassword] = useState(false);

  useEffect(() => {
    const fetchSuperAdminStatus = async () => {
      try {
        const result = await apiCall('/auth/has-super-admin', 'GET');
        setHasSuperAdmin(result.hasSuperAdmin);
      } catch (error) {
        console.error('Failed to fetch super admin status:', error);
      } finally {
        setRolesLoading(false);
      }
    };
    fetchSuperAdminStatus();
  }, []);

  const availableRoles = useMemo(() => {
    if (hasSuperAdmin) {
      return roleOptions.filter((role) => role.value !== 'super_admin');
    }
    return roleOptions;
  }, [hasSuperAdmin]);

  useEffect(() => {
    // If currently selected role is no longer available, switch to first available
    if (form.requestedRole === 'super_admin' && hasSuperAdmin) {
      setForm((current) => ({
        ...current,
        requestedRole: availableRoles[0]?.value || 'student',
      }));
    }
  }, [hasSuperAdmin, availableRoles]);

  const validatePassword = (pwd) => pwd.length >= 8;
  const validateEmail = (email) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

  const onChange = (event) => {
    const { name, value } = event.target;
    setForm((current) => ({
      ...current,
      [name]: value,
    }));
    // Clear error for this field
    if (errors[name]) {
      setErrors((current) => ({ ...current, [name]: '' }));
    }
  };

  const onSubmit = async (event) => {
    event.preventDefault();
    setLoading(true);
    setMessage('');
    setErrors({});

    const newErrors = {};
    if (!form.fullName.trim()) newErrors.fullName = 'Full name is required';
    if (!validateEmail(form.email)) newErrors.email = 'Valid email is required';
    if (!validatePassword(form.password)) newErrors.password = 'Password must be at least 8 characters';

    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      setLoading(false);
      return;
    }

    try {
      const result = await apiCall('/auth/signup', 'POST', form);
      setMessage(result.message || 'Signup submitted. Please wait for approval.');
      setForm({ fullName: '', email: '', password: '', requestedRole: 'student' });
      onSuccess?.();
    } catch (error) {
      setMessage({ text: error.message, type: 'error' });
    } finally {
      setLoading(false);
    }
  };

  return (
    <form className="auth-form" onSubmit={onSubmit}>
      <h2 className="form-title">Create Account</h2>
      <p className="form-subtitle">Create account for any role. Access starts after approval.</p>
      
      <div className="form-group">
        <label htmlFor="fullName">Full Name</label>
        <div className="input-wrapper">
          <span className="input-icon">👤</span>
          <input
            id="fullName"
            name="fullName"
            type="text"
            placeholder="Your full name"
            value={form.fullName}
            onChange={onChange}
            className={errors.fullName ? 'error' : ''}
          />
        </div>
        {errors.fullName && <p className="error-text">⚠️ {errors.fullName}</p>}
      </div>

      <div className="form-group">
        <label htmlFor="email">Email Address</label>
        <div className="input-wrapper">
          <span className="input-icon">✉️</span>
          <input
            id="email"
            name="email"
            type="email"
            placeholder="your@email.com"
            value={form.email}
            onChange={onChange}
            className={errors.email ? 'error' : ''}
          />
        </div>
        {errors.email && <p className="error-text">⚠️ {errors.email}</p>}
      </div>

      <div className="form-group">
        <label htmlFor="password">Password</label>
        <div className="input-wrapper">
          <span className="input-icon">🔒</span>
          <input
            id="password"
            name="password"
            type={showPassword ? 'text' : 'password'}
            placeholder="Min 8 characters"
            value={form.password}
            onChange={onChange}
            className={errors.password ? 'error' : ''}
          />
          <button
            type="button"
            className="toggle-password-btn"
            onClick={() => setShowPassword(!showPassword)}
            aria-label={showPassword ? 'Hide password' : 'Show password'}
            title={showPassword ? 'Hide password' : 'Show password'}
          >
            {showPassword ? '👁️' : '👁️‍🗨️'}
          </button>
        </div>
        {errors.password && <p className="error-text">⚠️ {errors.password}</p>}
        <p className="helper-text">Password must be at least 8 characters long</p>
      </div>

      <div className="form-group">
        <label htmlFor="role">Select Your Role</label>
        {rolesLoading ? (
          <div className="helper-text">Loading available roles...</div>
        ) : (
          <>
            <select
              id="role"
              name="requestedRole"
              value={form.requestedRole}
              onChange={onChange}
              disabled={rolesLoading}
            >
              {availableRoles.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.icon} {option.label}
                </option>
              ))}
            </select>
          </>
        )}
      </div>

      <button type="submit" className="btn-primary btn-large" disabled={loading}>
        {loading ? '⏳ Creating Account...' : '✨ Create Account'}
      </button>

      {message && (
        <div className={`alert ${typeof message === 'object' ? message.type : 'success'}`}>
          {typeof message === 'object' ? message.text : message}
        </div>
      )}
    </form>
  );
}

function SigninForm({ onSignedIn }) {
  const [form, setForm] = useState({ email: '', password: '' });
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');
  const [errors, setErrors] = useState({});
  const [showPassword, setShowPassword] = useState(false);

  const onChange = (event) => {
    const { name, value } = event.target;
    setForm((current) => ({
      ...current,
      [name]: value,
    }));
    if (errors[name]) {
      setErrors((current) => ({ ...current, [name]: '' }));
    }
  };

  const onSubmit = async (event) => {
    event.preventDefault();
    setLoading(true);
    setMessage('');
    setErrors({});

    const newErrors = {};
    if (!form.email) newErrors.email = 'Email is required';
    if (!form.password) newErrors.password = 'Password is required';

    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      setLoading(false);
      return;
    }

    try {
      const result = await apiCall('/auth/signin', 'POST', form);
      onSignedIn(result.user);
    } catch (error) {
      setMessage({ text: error.message, type: 'error' });
    } finally {
      setLoading(false);
    }
  };

  return (
    <form className="auth-form" onSubmit={onSubmit}>
      <h2 className="form-title">Sign In</h2>
      <p className="form-subtitle">Use your assigned credentials to access the system</p>
      
      <div className="form-group">
        <label htmlFor="signin-email">Email Address</label>
        <div className="input-wrapper">
          <span className="input-icon">✉️</span>
          <input
            id="signin-email"
            name="email"
            type="email"
            placeholder="your@email.com"
            value={form.email}
            onChange={onChange}
            className={errors.email ? 'error' : ''}
          />
        </div>
        {errors.email && <p className="error-text">⚠️ {errors.email}</p>}
      </div>

      <div className="form-group">
        <label htmlFor="signin-password">Password</label>
        <div className="input-wrapper">
          <span className="input-icon">🔒</span>
          <input
            id="signin-password"
            name="password"
            type={showPassword ? 'text' : 'password'}
            placeholder="Enter your password"
            value={form.password}
            onChange={onChange}
            className={errors.password ? 'error' : ''}
          />
          <button
            type="button"
            className="toggle-password-btn"
            onClick={() => setShowPassword(!showPassword)}
            aria-label={showPassword ? 'Hide password' : 'Show password'}
            title={showPassword ? 'Hide password' : 'Show password'}
          >
            {showPassword ? '👁️' : '👁️‍🗨️'}
          </button>
        </div>
        {errors.password && <p className="error-text">⚠️ {errors.password}</p>}
      </div>

      <button type="submit" className="btn-primary btn-large" disabled={loading}>
        {loading ? '⏳ Signing in...' : '🔓 Sign In'}
      </button>

      {message && (
        <div className={`alert ${typeof message === 'object' ? message.type : 'success'}`}>
          {typeof message === 'object' ? message.text : message}
        </div>
      )}
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
          <div>
            <h2>Welcome, {user.fullName} 👋</h2>
            <p className="helper">You're now logged into the LMS</p>
          </div>
          <button onClick={onSignOut} className="btn-secondary">Sign Out</button>
        </div>
        <div className="dashboard-info">
          <div className="info-item">
            <span className="info-label">Email:</span>
            <span className="info-value">{user.email}</span>
          </div>
          <div className="info-item">
            <span className="info-label">Status:</span>
            <span className="info-value"><code>{user.status}</code></span>
          </div>
          <div className="info-item">
            <span className="info-label">Roles:</span>
            <span className="info-value">{user.roles.length ? user.roles.join(', ') : 'No active roles'}</span>
          </div>
        </div>
      </div>
      <ApprovalPanel user={user} />
    </main>
  );
}

export default function App() {
  const [user, setUser] = useState(null);
  const [ready, setReady] = useState(false);
  const [authTab, setAuthTab] = useState('signin');

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
    return (
      <main className="container">
        <div className="loading-container">
          <p className="loading-text">⏳ Loading...</p>
        </div>
      </main>
    );
  }

  if (user) {
    return <Dashboard user={user} onSignOut={onSignOut} />;
  }

  return (
    <main className="auth-container">
      <div className="auth-wrapper">
        {/* Left Hero Section */}
        <div className="hero-section">
          <div className="hero-content">
            <h1 className="hero-title">📚 LMS Portal</h1>
            <p className="hero-subtitle">Learning Management System</p>
            <p className="hero-description">
              Join our learning community. Sign in to access your courses, or create a new account to get started.
            </p>
            <ul className="hero-features">
              <li>✨ Easy account creation</li>
              <li>🔐 Secure authentication</li>
              <li>👥 Multiple user roles</li>
              <li>⚡ Role-based access</li>
            </ul>
          </div>
        </div>

        {/* Right Auth Section */}
        <div className="auth-section">
          <div className="auth-tabs">
            <button
              className={`tab-btn ${authTab === 'signin' ? 'active' : ''}`}
              onClick={() => setAuthTab('signin')}
            >
              Sign In
            </button>
            <button
              className={`tab-btn ${authTab === 'signup' ? 'active' : ''}`}
              onClick={() => setAuthTab('signup')}
            >
              Sign Up
            </button>
          </div>

          <div className="tab-content">
            {authTab === 'signin' ? (
              <SigninForm onSignedIn={onSignedIn} />
            ) : (
              <SignupForm onSuccess={() => setAuthTab('signin')} />
            )}
          </div>

          <div className="auth-footer">
            <p className="footer-text">
              {authTab === 'signin'
                ? "Don't have an account? "
                : 'Already have an account? '}
              <button
                className="link-btn"
                onClick={() => setAuthTab(authTab === 'signin' ? 'signup' : 'signin')}
              >
                {authTab === 'signin' ? 'Sign Up' : 'Sign In'}
              </button>
            </p>
          </div>
        </div>
      </div>
    </main>
  );
}
