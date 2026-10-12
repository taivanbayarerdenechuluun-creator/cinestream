'use client';

export default function WatchNowLink({ movieId, title }: { movieId: number; title: string }) {
  const path = `/movie-streaming/watch.php?id=${movieId}`;

  function openPhpPlayer() {
    const configuredBase = process.env.NEXT_PUBLIC_PHP_BASE_URL;
    const phpPath = configuredBase
      ? `${configuredBase.replace(/\/$/, '')}/watch.php?id=${movieId}`
      : path;
    const url = new URL(phpPath, window.location.origin);

    // Next dev uses its own port; XAMPP serves this project's PHP pages on the default web port.
    if (!configuredBase && window.location.port) url.port = '';
    window.location.assign(url.toString());
  }

  return (
    <a
      href={path}
      className="primary-button"
      aria-label={`Watch ${title}`}
      onClick={(event) => {
        event.preventDefault();
        openPhpPlayer();
      }}
    >
      Watch now
    </a>
  );
}
