export function detectAppBasePath() {
  if (typeof window === 'undefined') return '';
  const pathname = window.location.pathname;
  const markers = ['/Version%202', '/Version 2'];
  for (const marker of markers) {
    const idx = pathname.indexOf(marker);
    if (idx >= 0) return pathname.slice(0, idx + marker.length);
  }
  return '';
}

export function resolveAssetPath(path) {
  const value = (path || '').trim();
  if (!value || /^(https?:)?\/\//i.test(value) || value.startsWith('data:')) return value;
  if (value.startsWith('/')) return value;
  return `${detectAppBasePath()}/${value.replace(/^(\.\.\/)+/, '')}`.replace(/([^:]\/)\/+/g, '$1');
}
