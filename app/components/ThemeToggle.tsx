'use client';

import { useEffect, useState } from 'react';

type Theme = 'dark' | 'light';

export default function ThemeToggle() {
  const [theme, setTheme] = useState<Theme>('dark');

  useEffect(() => {
    let saved: string | null = null;
    try {
      saved = window.localStorage.getItem('cinestream-theme');
    } catch {
      // Keep the default theme when browser storage is unavailable.
    }
    const activeTheme: Theme = saved === 'light' ? 'light' : 'dark';
    setTheme(activeTheme);
    document.documentElement.dataset.theme = activeTheme;
  }, []);

  function toggleTheme() {
    const nextTheme = theme === 'dark' ? 'light' : 'dark';
    setTheme(nextTheme);
    document.documentElement.dataset.theme = nextTheme;
    try {
      window.localStorage.setItem('cinestream-theme', nextTheme);
    } catch {
      // The active page theme still changes when storage is unavailable.
    }
  }

  return (
    <button
      type="button"
      className="theme-toggle"
      onClick={toggleTheme}
      aria-label={`Switch to ${theme === 'dark' ? 'light' : 'dark'} mode`}
      title={`Switch to ${theme === 'dark' ? 'light' : 'dark'} mode`}
    >
      <svg aria-hidden="true" viewBox="0 0 24 24" className="theme-icon">
        {theme === 'dark' ? (
          <>
            <circle cx="12" cy="12" r="3.7" />
            <path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" />
          </>
        ) : (
          <path d="M20.2 15.4A8.4 8.4 0 0 1 8.6 3.8 8.5 8.5 0 1 0 20.2 15.4Z" />
        )}
      </svg>
      <span className="theme-toggle-label">{theme === 'dark' ? 'Light' : 'Dark'} mode</span>
    </button>
  );
}
