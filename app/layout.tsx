import './globals.css';
import type { Metadata } from 'next';
import Link from 'next/link';

export const metadata: Metadata = {
  title: 'CineStream',
  description: 'Modern movie streaming catalog built with Next.js',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>
        <header className="site-header">
          <div className="nav-shell">
            <Link href="/" className="brand" aria-label="CineStream home">
              Cine<span>Stream</span>
            </Link>
            <nav className="main-nav" aria-label="Main navigation">
              <Link href="/">Home</Link>
              <Link href="/movies">Movies</Link>
              <Link href="/#popular">Popular</Link>
              <Link href="/#top-rated">Top Rated</Link>
              <Link href="/#upcoming">Upcoming</Link>
            </nav>
            <div className="nav-actions">
              <Link href="/movies" className="ghost-button">
                Browse
              </Link>
              <Link href="/movies" className="primary-button">
                Start Watching
              </Link>
            </div>
          </div>
        </header>
        {children}
      </body>
    </html>
  );
}
