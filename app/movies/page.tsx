import { allMovies } from '@/lib/movie-data';
import MovieCard from '../components/MovieCard';
import ScrollReveal from '../components/ScrollReveal';

export default function MoviesPage() {
  return (
    <main className="page-shell inner-page">
      <ScrollReveal />
      <div className="content-wrap">
        <section className="page-header scroll-reveal">
          <p className="eyebrow">Browse the collection</p>
          <h1>All Movies</h1>
          <p className="catalog-count">{allMovies.length} titles to explore</p>
        </section>
        <div className="movie-grid wide-grid catalog-grid">
          {allMovies.map((movie) => <MovieCard key={movie.id} movie={movie} />)}
        </div>
      </div>
    </main>
  );
}
