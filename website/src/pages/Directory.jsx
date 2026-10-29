import { useState, useMemo } from 'react';
import { useApi } from '../hooks/useApi';
import ArchitectCard from '../components/ArchitectCard';
import { IconSearch, IconEmptyDirectory } from '../components/Icons';
import './Directory.css';

const SPECIALTIES = ['All', 'Residential', 'Commercial', 'Interior', 'Heritage', 'Sustainable', 'Industrial'];

export default function Directory() {
  const { data: members, loading, error } = useApi('/api/members.php');
  const [search, setSearch] = useState('');
  const [specialty, setSpecialty] = useState('All');

  const filtered = useMemo(() => {
    if (!members) return [];
    return members.filter((m) => {
      const q = search.toLowerCase();
      const matchSearch =
        !q ||
        m.name.toLowerCase().includes(q) ||
        (m.id_number || '').toLowerCase().includes(q) ||
        (m.company_name || '').toLowerCase().includes(q) ||
        (m.location || '').toLowerCase().includes(q) ||
        (m.specialty || '').toLowerCase().includes(q);
      const matchSpec =
        specialty === 'All' ||
        (m.specialty || '').toLowerCase().includes(specialty.toLowerCase());
      return matchSearch && matchSpec;
    });
  }, [members, search, specialty]);

  return (
    <main className="page-container">
      {/* Page Hero */}
      <div className="dir-hero">
        <div className="container reveal-pop">
          <p className="eyebrow">Quezon Province Chapter</p>
          <h1 className="display-2">Architect <span className="text-gold">Directory</span></h1>
          <p className="body-lg muted" style={{ marginTop: 12, maxWidth: 520 }}>
            Verified, licensed architects of the UAP Quezon Province Chapter.
          </p>
        </div>
      </div>

      <div className="container dir-body">
        {/* Filters */}
        <div className="dir-filters reveal-pop">
          <div className="dir-search-wrap">
            <IconSearch size={18} className="dir-search-icon" />
            <input
              type="text"
              placeholder="Search by name, company, specialty, address…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="input dir-search"
            />
          </div>

          <div className="dir-spec-filters">
            {SPECIALTIES.map((s) => (
              <button
                key={s}
                onClick={() => setSpecialty(s)}
                className={`spec-tag${specialty === s ? ' active' : ''}`}
              >
                {s}
              </button>
            ))}
          </div>
        </div>

        {/* Count */}
        <p className="dir-count muted reveal">
          {loading ? 'Loading…' : `${filtered.length} architect${filtered.length !== 1 ? 's' : ''} found`}
        </p>

        {/* Grid */}
        {error ? (
          <div className="dir-error reveal-pop">
            <p>Unable to load directory. Please try again later.</p>
          </div>
        ) : loading ? (
          <div className="grid-3 dir-grid">
            {[...Array(6)].map((_, i) => (
              <div key={i} className="skeleton" style={{ height: 360, borderRadius: 'var(--r-lg)' }} />
            ))}
          </div>
        ) : filtered.length > 0 ? (
          <div className="grid-3 dir-grid reveal-stagger">
            {filtered.map((m) => <ArchitectCard key={m.id} member={m} />)}
          </div>
        ) : (
          <div className="dir-empty reveal-pop">
            <div className="dir-empty-icon">
              <IconEmptyDirectory size={44} />
            </div>
            <h3>No architects found</h3>
            <p className="muted">Try adjusting your search or specialty filter.</p>
            <button className="btn btn-ghost" onClick={() => { setSearch(''); setSpecialty('All'); }}>
              Clear Filters
            </button>
          </div>
        )}
      </div>
    </main>
  );
}
