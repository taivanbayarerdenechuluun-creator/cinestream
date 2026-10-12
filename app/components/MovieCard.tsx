'use client';

import Image from 'next/image';
import Link from 'next/link';
import type { Movie } from '@/lib/movie-data';

export default function MovieCard({ movie }: { movie: Movie }) {
  return (
    <Link href={`/movie/${movie.id}`} className="movie-card" aria-label={`View ${movie.title}`}>
      <div className="poster-wrap">
        <Image
          src={movie.poster}
          alt={`${movie.title} poster`}
          fill
          className="movie-poster"
          sizes="(max-width: 600px) 68vw, (max-width: 980px) 40vw, 240px"
          onError={(event) => {
            event.currentTarget.onerror = null;
            event.currentTarget.srcset = '';
            event.currentTarget.src = '/movie-placeholder.svg';
          }}
        />
        <span className="movie-badge">{movie.badge}</span>
        <span className="poster-play" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="m9 6 10 6-10 6z" /></svg>
        </span>
      </div>
      <div className="movie-info">
        <div className="movie-topline">
          <span>{movie.year}</span>
          <span className="rating"><span aria-hidden="true">★</span> {movie.rating}</span>
        </div>
        <h3>{movie.title}</h3>
        <p>{movie.subtitle}</p>
        <div className="meta-row">
          <span>{movie.duration}</span>
          <span>{movie.genres.slice(0, 2).join(' · ')}</span>
        </div>
      </div>
    </Link>
  );
}
