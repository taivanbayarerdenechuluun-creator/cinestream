import Image from 'next/image';
import Link from 'next/link';
import { featuredMovie, movieSections } from '@/lib/movie-data';

function MovieCard({ title, subtitle, year, rating, duration, genres, poster, badge, id }: any) {
  return (
    <Link href={`/movie/${id}`} className="movie-card" aria-label={`Open ${title}`}>
      <div className="poster-wrap">
        <Image src={poster} alt={title} fill className="movie-poster" sizes="(max-width: 768px) 50vw, 33vw" />
        <span className="movie-badge">{badge}</span>
      </div>
      <div className="movie-info">
        <div className="movie-topline">
          <span>{year}</span>
          <span>★ {rating}</span>
        </div>
        <h3>{title}</h3>
        <p>{subtitle}</p>
        <div className="meta-row">
          <span>{duration}</span>
          <span>{genres.slice(0, 2).join(' • ')}</span>
        </div>
      </div>
    </Link>
  );
}

export default function HomePage() {
  return (
    <main className="page-shell">
      <section className="hero-section">
        <div className="hero-overlay" />
        <Image
          src={featuredMovie.backdrop}
          alt={featuredMovie.title}
          fill
          priority
          className="hero-backdrop"
          sizes="100vw"
        />
        <div className="hero-content">
          <span className="eyebrow">Featured tonight</span>
          <h1>{featuredMovie.title}</h1>
          <p className="hero-subtitle">{featuredMovie.subtitle}</p>
          <div className="hero-meta">
            <span>{featuredMovie.year}</span>
            <span>{featuredMovie.duration}</span>
            <span>{featuredMovie.genres.join(' • ')}</span>
          </div>
          <div className="hero-actions">
            <Link href={`/movie/${featuredMovie.id}`} className="primary-button large-button">
              Watch now
            </Link>
            <Link href="/movies" className="ghost-button large-button">
              View all
            </Link>
          </div>
        </div>
      </section>

      <section className="content-wrap">
        {movieSections.map((section) => (
          <div key={section.key} id={section.key} className="movie-section">
            <div className="section-heading">
              <div>
                <p className="eyebrow">Trending</p>
                <h2>{section.title}</h2>
              </div>
              <Link href="/movies" className="section-link">
                View all
              </Link>
            </div>
            <div className="movie-grid">
              {section.movies.map((movie) => (
                <MovieCard key={movie.id} {...movie} />
              ))}
            </div>
          </div>
        ))}
      </section>
    </main>
  );
}
