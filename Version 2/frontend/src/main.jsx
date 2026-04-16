import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import { AuthProvider } from './state/AuthProvider';
import './styles.css';

function getRouterBasename() {
  const explicit = import.meta.env.VITE_ROUTER_BASENAME;
  if (explicit && explicit.trim() !== '') {
    return explicit.trim();
  }

  if (typeof window === 'undefined') {
    return '/';
  }

  const pathname = window.location.pathname;
  const markers = ['/Version%202', '/Version 2'];
  for (const marker of markers) {
    const index = pathname.indexOf(marker);
    if (index >= 0) {
      return pathname.slice(0, index + marker.length);
    }
  }

  return '/';
}

createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <BrowserRouter basename={getRouterBasename()}>
      <AuthProvider>
        <App />
      </AuthProvider>
    </BrowserRouter>
  </React.StrictMode>
);
