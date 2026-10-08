import Image from 'next/image';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { getMovieById, movieSections } from '@/lib/movie-data';

export default function MovieDetailPage({ params }: { params: { id: string } }) {
  const movie = getMovieById(params.id);

  if (!movie) {
    notFound();
  }

  const related = movieSections.find((section) => section.key === movie.category)?.movies.filter((item) => item.id !== movie.id).slice(0, 4) ?? [];

  return (
    <main className="page-shell inner-page">
      <article className="movie-detail">
        <Image src={movie.backdrop} alt={movie.title} fill className="detail-backdrop" sizes="100vw" />
        <div className="detail-content">
          <div className="detail-poster">
            <Image src={movie.poster} alt={movie.title} fill className="movie-poster" sizes="(max-width: 760px) 100vw, 280px" />
          </div>

          <div className="detail-body">
            <span className="eyebrow">{movie.badge}</span>
            <h1>{movie.title}</h1>
            <div className="detail-meta">
              <span>{movie.year}</span>
              <span>{movie.duration}</span>
              <span>{movie.rating} ★</span>
              {movie.genres.map((genre) => (
                <span key={genre}>{genre}</span>
              ))}
            </div>
            <p className="summary">{movie.description}</p>
            <div className="detail-actions">
              <Link href="/movies" className="primary-button">
                Watch trailer
              </Link>
              <Link href="/movies" className="ghost-button">
                Browse more
              </Link>
            </div>
          </div>
        </div>
      </article>

      <section className="movie-section">
        <div className="section-heading">
          <div>
            <p className="eyebrow">More like this</p>
            <h2>Recommended</h2>
          </div>
          <Link href="/movies" className="section-link">
            View all
          </Link>
        </div>

        <div className="movie-grid">
          {related.map((item) => (
            <Link href={`/movie/${item.id}`} key={item.id} className="movie-card">
              <div className="poster-wrap">
                <Image src={item.poster} alt={item.title} fill className="movie-poster" sizes="(max-width: 768px) 50vw, 25vw" />
                <span className="movie-badge">{item.badge}</span>
              </div>
              <div className="movie-info">
                <div className="movie-topline">
                  <span>{item.year}</span>
                  <span>★ {item.rating}</span>
                </div>
                <h3>{item.title}</h3>
                <p>{item.subtitle}</p>
                <div className="meta-row">
                  <span>{item.duration}</span>
                  <span>{item.genres.slice(0, 2).join(' • ')}</span>
                </div>
              </div>
            </Link>
          ))}
        </div>
      </section>
    </main>
  );
}
