import Image from 'next/image';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { getMovieById, movieSections } from '@/lib/movie-data';
import MovieCard from '../../components/MovieCard';
import ScrollReveal from '../../components/ScrollReveal';
import WatchNowLink from '../../components/WatchNowLink';

export default function MovieDetailPage({ params }: { params: { id: string } }) {
  const movie = getMovieById(params.id);
  if (!movie) notFound();

  const related = movieSections
    .find((section) => section.key === movie.category)?.movies
    .filter((item) => item.id !== movie.id)
    .slice(0, 4) ?? [];
  const trailerSearch = `https://www.youtube.com/results?search_query=${encodeURIComponent(`${movie.title} ${movie.year} official trailer`)}`;

  return (
    <main className="page-shell inner-page">
      <ScrollReveal />
      <article className="movie-detail scroll-reveal">
        <Image src={movie.backdrop} alt="" fill className="detail-backdrop" sizes="100vw" />
        <div className="detail-content">
          <div className="detail-poster">
            <Image src={movie.poster} alt={`${movie.title} poster`} fill className="movie-poster" sizes="(max-width: 760px) 80vw, 280px" />
          </div>
          <div className="detail-body">
            <span className="eyebrow">{movie.badge}</span>
            <h1>{movie.title}</h1>
            <div className="detail-meta">
              <span>{movie.year}</span>
              <span>{movie.duration}</span>
              <span><span aria-hidden="true">★</span> {movie.rating}</span>
              {movie.genres.map((genre) => <span key={genre}>{genre}</span>)}
            </div>
            <p className="summary">{movie.description}</p>
            <div className="detail-actions">
              <WatchNowLink movieId={movie.id} title={movie.title} />
              <a href={trailerSearch} className="ghost-button" target="_blank" rel="noreferrer">Watch trailer</a>
              <Link href="/movies" className="ghost-button">Browse more</Link>
            </div>
          </div>
        </div>
      </article>

      <section className="movie-section scroll-reveal">
        <div className="section-heading">
          <div><p className="eyebrow">More like this</p><h2>Recommended</h2></div>
          <Link href="/movies" className="section-link">View all</Link>
        </div>
        <div className="movie-grid wide-grid">
          {related.map((item) => <MovieCard key={item.id} movie={item} />)}
        </div>
      </section>
    </main>
  );
}
