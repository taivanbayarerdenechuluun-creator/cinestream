'use client';

import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { allMovies } from '@/lib/movie-data';
import MovieCard from '../components/MovieCard';

export default function SearchExperience() {
  const [query, setQuery] = useState('');
  const results = useMemo(() => {
    const normalizedQuery = query.trim().toLocaleLowerCase();
    if (!normalizedQuery) return [];
    return allMovies.filter((movie) => [
      movie.title,
      movie.subtitle,
      movie.description,
      movie.year,
      ...movie.genres,
    ].join(' ').toLocaleLowerCase().includes(normalizedQuery));
  }, [query]);

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
  }

  return (
    <>
      <form className="catalog-search" role="search" onSubmit={submit}>
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8" /><path d="m16 16 5 5" /></svg>
        <label className="sr-only" htmlFor="movie-search">Search titles, genres, or years</label>
        <input
          id="movie-search"
          type="search"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          onKeyDown={(event) => { if (event.key === 'Escape') setQuery(''); }}
          placeholder="Search titles, genres, or years"
          autoComplete="off"
        />
        {query && <button type="button" className="search-clear" onClick={() => setQuery('')} aria-label="Clear search">Clear</button>}
      </form>

      <div className="search-status" aria-live="polite" aria-atomic="true">
        {!query.trim() ? 'Search the movie collection by title, genre, or year.' : `${results.length} ${results.length === 1 ? 'title' : 'titles'} found`}
      </div>

      {!query.trim() ? (
        <section className="search-empty">
          <span className="eyebrow">Find your next film</span>
          <h2>What are you in the mood for?</h2>
          <p>Try a title, a genre like “drama,” or a release year.</p>
        </section>
      ) : results.length ? (
        <div className="movie-grid wide-grid catalog-grid">
          {results.map((movie) => <MovieCard key={movie.id} movie={movie} />)}
        </div>
      ) : (
        <section className="search-empty" role="status">
          <span className="eyebrow">No matches</span>
          <h2>Try another search</h2>
          <p>Check the spelling or search by a genre or year.</p>
          <button type="button" className="ghost-button" onClick={() => setQuery('')}>Clear search</button>
        </section>
      )}
    </>
  );
}
