import SearchExperience from './SearchExperience';

export default function SearchPage() {
  return (
    <main className="page-shell inner-page">
      <div className="content-wrap search-page">
        <header className="page-header">
          <p className="eyebrow">The CineStream library</p>
          <h1>Find a film</h1>
          <p className="catalog-count">Search the collection by title, genre, or release year.</p>
        </header>
        <SearchExperience />
      </div>
    </main>
  );
}
