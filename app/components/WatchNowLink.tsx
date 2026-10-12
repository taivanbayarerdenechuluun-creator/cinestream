import Link from 'next/link';

export default function WatchNowLink({ movieId, title }: { movieId: number; title: string }) {
  return (
    <Link
      href={`/movie-streaming/watch.php?id=${movieId}`}
      className="primary-button"
      aria-label={`Watch ${title}`}
    >
      Watch now
    </Link>
  );
}
