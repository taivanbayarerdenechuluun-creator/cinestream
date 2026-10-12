'use client';

import { useEffect, useRef, useState } from 'react';
import type { Movie } from '@/lib/movie-data';
import MovieCard from './MovieCard';

export default function MovieCarousel({ title, movies }: { title: string; movies: Movie[] }) {
  const rail = useRef<HTMLDivElement>(null);
  const [position, setPosition] = useState({ start: true, end: false });

  function updatePosition() {
    const element = rail.current;
    if (!element) return;
    setPosition({
      start: element.scrollLeft <= 2,
      end: element.scrollLeft + element.clientWidth >= element.scrollWidth - 2,
    });
  }

  function scroll(direction: -1 | 1) {
    rail.current?.scrollBy({ left: direction * (rail.current.clientWidth * 0.82), behavior: 'smooth' });
  }

  useEffect(() => {
    const element = rail.current;
    if (!element) return;
    const measure = () => {
      setPosition({
        start: element.scrollLeft <= 2,
        end: element.scrollLeft + element.clientWidth >= element.scrollWidth - 2,
      });
    };
    measure();
    if (typeof ResizeObserver !== 'undefined') {
      const resizeObserver = new ResizeObserver(measure);
      resizeObserver.observe(element);
      return () => resizeObserver.disconnect();
    }
    window.addEventListener('resize', measure, { passive: true });
    return () => window.removeEventListener('resize', measure);
  }, [movies.length]);

  return (
    <div className="carousel-shell">
      <div
        className="movie-rail"
        ref={rail}
        onScroll={updatePosition}
        role="region"
        aria-label={`${title} movies`}
        tabIndex={0}
      >
        {movies.map((movie) => <MovieCard key={movie.id} movie={movie} />)}
      </div>
      <div className="carousel-controls" aria-label={`${title} carousel controls`}>
        <button type="button" className="rail-arrow" onClick={() => scroll(-1)} disabled={position.start} aria-label={`Previous ${title} movies`}>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </button>
        <button type="button" className="rail-arrow" onClick={() => scroll(1)} disabled={position.end} aria-label={`Next ${title} movies`}>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
        </button>
      </div>
    </div>
  );
}
