import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../state/AuthProvider';
import { api } from '../../api/client';

export function LoginPage() {
  const { login, error } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [localError, setLocalError] = useState('');

  const handleSocialLogin = async (provider) => {
    setLocalError('');
    try {
      const res = await api.socialLogin(provider);
      navigate(res?.user?.role === 'admin' ? '/admin/pages/analytics' : '/customer/dashboard');
    } catch (err) {
      setLocalError(err.message || 'Social login failed');
    }
  };

  return (
    <div className="auth-wrapper">
      <div className="auth-box">
        <div className="auth-left">
          <h2 className="mb-3">Welcome Back</h2>
          <p className="mb-4">Login to manage your reservations.</p>
          {(localError || error) && <div className="alert alert-danger">{localError || error}</div>}
          <form onSubmit={async (e) => {
            e.preventDefault();
            setLocalError('');
            try {
              const res = await login(email, password);
              navigate(res?.user?.role === 'admin' ? '/admin/pages/analytics' : '/customer/dashboard');
            } catch (err) {
              setLocalError(err.message || 'Login failed');
            }
          }}>
            <div className="mb-3"><input type="email" className="form-control" placeholder="Email Address" value={email} onChange={(e) => setEmail(e.target.value)} required /></div>
            <div className="mb-3 password-wrapper">
              <input type={showPassword ? 'text' : 'password'} className="form-control" placeholder="Password" value={password} onChange={(e) => setPassword(e.target.value)} required />
              <span className="eye-icon" onClick={() => setShowPassword((v) => !v)}><i className={`fa-regular ${showPassword ? 'fa-eye-slash' : 'fa-eye'}`}></i></span>
            </div>
            <button className="btn btn-main w-100" type="submit">Login</button>
          </form>
          <div className="divider">OR</div>
          <div className="d-grid gap-2 mb-3">
            <button type="button" className="social-btn" onClick={() => handleSocialLogin('google')}><img src="https://img.icons8.com/color/20/google-logo.png" alt="google" />Continue with Google</button>
            <button type="button" className="social-btn" onClick={() => handleSocialLogin('apple')}><img src="https://img.icons8.com/ios-filled/20/000000/mac-os.png" alt="apple" />Continue with Apple</button>
          </div>
          <div className="text-center mt-3">Don&apos;t have an account? <Link to="/auth/register">Create Account</Link></div>
        </div>
        <div className="auth-right">
          <div className="auth-overlay"></div>
          <div className="auth-right-content">
            <h3>Reserve in Seconds</h3>
            <p>Modern dining starts here.</p>
          </div>
        </div>
      </div>
    </div>
  );
}
