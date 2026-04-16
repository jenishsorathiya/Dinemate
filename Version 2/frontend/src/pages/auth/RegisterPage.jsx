import { useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../state/AuthProvider';

export function RegisterPage() {
  const { register } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ name: '', email: '', phone: '', password: '', confirm_password: '' });
  const [error, setError] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const passwordStrength = useMemo(() => {
    if (!form.password) return 0;
    if (form.password.length < 6) return 30;
    if (form.password.length < 10) return 60;
    return 100;
  }, [form.password]);
  return (
    <div className="auth-wrapper">
      <div className="auth-box">
        <div className="auth-left">
          <h2>Create Account</h2>
          <p className="mb-4">Join DineMate and book effortlessly.</p>
          {error && <div className="alert alert-danger">{error}</div>}
          <form onSubmit={async (e) => {
            e.preventDefault();
            setError('');
            if (form.password !== form.confirm_password) {
              setError('Passwords do not match');
              return;
            }
            try {
              await register(form);
              navigate('/customer/dashboard');
            } catch (err) {
              setError(err.message || 'Registration failed');
            }
          }}>
            <div className="mb-3"><input className="form-control" placeholder="Full Name" value={form.name} onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))} required /></div>
            <div className="mb-3"><input type="email" className="form-control" placeholder="Email Address" value={form.email} onChange={(e) => setForm((p) => ({ ...p, email: e.target.value }))} required /></div>
            <div className="mb-3"><input className="form-control" placeholder="Phone Number" value={form.phone} onChange={(e) => setForm((p) => ({ ...p, phone: e.target.value }))} /></div>
            <div className="mb-3 password-wrapper">
              <input type={showPassword ? 'text' : 'password'} className="form-control" placeholder="Password" value={form.password} onChange={(e) => setForm((p) => ({ ...p, password: e.target.value }))} required />
              <span className="eye" onClick={() => setShowPassword((v) => !v)}><i className={`fa-regular ${showPassword ? 'fa-eye-slash' : 'fa-eye'}`}></i></span>
              <div className={`strength ${passwordStrength <= 30 ? 'bg-danger' : passwordStrength < 100 ? 'bg-warning' : 'bg-success'}`} style={{ width: `${passwordStrength}%` }}></div>
            </div>
            <div className="mb-3">
              <input type="password" className="form-control" placeholder="Confirm Password" value={form.confirm_password} onChange={(e) => setForm((p) => ({ ...p, confirm_password: e.target.value }))} required />
              {form.confirm_password && <small style={{ color: form.password === form.confirm_password ? 'green' : 'red' }}>{form.password === form.confirm_password ? '✓ Passwords match' : '✗ Passwords do not match'}</small>}
            </div>
            <button className="btn btn-main w-100 mb-3" type="submit">Register</button>
          </form>
          <div className="text-center">Already have an account? <Link to="/auth/login">Sign In</Link></div>
        </div>
        <div className="auth-right auth-register-right">
          <div className="auth-overlay"></div>
          <div className="auth-right-content">
            <h3>Premium Dining Experience</h3>
            <p>Reserve smart. Dine better.</p>
          </div>
        </div>
      </div>
    </div>
  );
}
