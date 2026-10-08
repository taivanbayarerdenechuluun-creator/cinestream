import Link from 'next/link';
import Image from 'next/image';
import { allMovies } from '@/lib/movie-data';

export default function MoviesPage() {
  return (
    <main className="page-shell inner-page">
      <div className="content-wrap">
        <section className="page-header">
          <p className="eyebrow">Browse</p>
          <h1>All Movies</h1>
        </section>

        <div className="movie-grid wide-grid">
          {allMovies.map((movie) => (
            <Link href={`/movie/${movie.id}`} key={movie.id} className="movie-card" aria-label={`Open ${movie.title}`}>
              <div className="poster-wrap">
                <Image src={movie.poster} alt={movie.title} fill className="movie-poster" sizes="(max-width: 768px) 50vw, 25vw" />
                <span className="movie-badge">{movie.badge}</span>
              </div>
              <div className="movie-info">
                <div className="movie-topline">
                  <span>{movie.year}</span>
                  <span>★ {movie.rating}</span>
                </div>
                <h3>{movie.title}</h3>
                <p>{movie.subtitle}</p>
                <div className="meta-row">
                  <span>{movie.duration}</span>
                  <span>{movie.genres.slice(0, 2).join(' • ')}</span>
                </div>
              </div>
            </Link>
          ))}
        </div>
      </div>
    </main>
  );
}
