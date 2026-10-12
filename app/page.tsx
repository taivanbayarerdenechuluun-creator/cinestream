import Image from 'next/image';
import Link from 'next/link';
import { featuredMovie, movieSections } from '@/lib/movie-data';
import MovieCarousel from './components/MovieCarousel';
import ScrollReveal from './components/ScrollReveal';

export default function HomePage() {
  return (
    <main className="page-shell">
      <ScrollReveal />
      <section className="hero-section scroll-reveal">
        <div className="hero-overlay" />
        <Image src={featuredMovie.backdrop} alt="" fill priority className="hero-backdrop" sizes="100vw" />
        <div className="hero-content">
          <span className="eyebrow">Featured tonight</span>
          <h1>{featuredMovie.title}</h1>
          <p className="hero-subtitle">{featuredMovie.subtitle}</p>
          <div className="hero-meta">
            <span>{featuredMovie.year}</span>
            <span>{featuredMovie.duration}</span>
            <span>{featuredMovie.genres.join(' · ')}</span>
            <span className="hero-rating"><span aria-hidden="true">★</span> {featuredMovie.rating}</span>
          </div>
          <div className="hero-actions">
            <Link href={`/movie/${featuredMovie.id}`} className="primary-button large-button">Watch now</Link>
            <Link href={`/movie/${featuredMovie.id}`} className="ghost-button large-button">More info</Link>
          </div>
        </div>
      </section>

      <div className="content-wrap">
        {movieSections.map((section) => (
          <section key={section.key} id={section.key} className="movie-section scroll-reveal">
            <div className="section-heading">
              <div>
                <p className="eyebrow">Discover</p>
                <h2>{section.title}</h2>
              </div>
              <Link href="/movies" className="section-link">View all</Link>
            </div>
            <MovieCarousel title={section.title} movies={section.movies} />
          </section>
        ))}
      </div>
    </main>
  );
}
