import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const refreshUser = async () => {
    try {
      setError('');
      const data = await api.authMe();
      setUser(data?.authenticated ? data.user : null);
    } catch (err) {
      setError(err.message || 'Failed to load session');
      setUser(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    refreshUser();
  }, []);

  const login = async (email, password) => {
    setError('');
    const data = await api.login({ email, password });
    setUser(data.user || null);
    return data;
  };

  const register = async (payload) => {
    setError('');
    const data = await api.register(payload);
    setUser(data.user || null);
    return data;
  };

  const logout = async () => {
    await api.logout();
    setUser(null);
  };

  const value = useMemo(
    () => ({
      user,
      loading,
      error,
      login,
      register,
      logout,
      refreshUser,
      authenticated: Boolean(user),
    }),
    [user, loading, error]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return context;
}
