export default function Loading() {
  return (
    <main className="page-shell inner-page" aria-label="Loading movies">
      <div className="loading-heading skeleton" />
      <div className="movie-grid wide-grid">
        {Array.from({ length: 8 }, (_, index) => (
          <div className="skeleton-card" key={index} aria-hidden="true">
            <div className="skeleton-poster skeleton" />
            <div className="skeleton-line skeleton" />
            <div className="skeleton-line short skeleton" />
          </div>
        ))}
      </div>
      <p className="sr-only" role="status">Loading movie listings</p>
    </main>
  );
}
