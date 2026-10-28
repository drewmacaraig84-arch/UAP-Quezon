import { useState, useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import {
  IconStar,
  IconSparkles,
  IconHandshake,
  IconExternalLink,
  IconBuilding,
  IconVerified,
  IconDraftingCompass,
  IconGrid
} from '../components/Icons';
import './Sponsors.css';

export default function Sponsors() {
  const { data: sponsors, loading, error } = useApi('/api/sponsors.php');
  const [activeTier, setActiveTier] = useState('all');
  const location = useLocation();

  // Scroll to targeted sponsor if hash provided
  useEffect(() => {
    if (location.hash && !loading) {
      const el = document.querySelector(location.hash);
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }
  }, [location, loading]);

  const items = sponsors && Array.isArray(sponsors) ? sponsors : [];

  const platinumCount = items.filter((s) => s.is_platinum === 1).length;
  const standardCount = items.length - platinumCount;

  const filteredItems = items.filter((s) => {
    if (activeTier === 'platinum') return s.is_platinum === 1;
    if (activeTier === 'standard') return !s.is_platinum;
    return true;
  });

  return (
    <main className="page-container sponsors-page">
      {/* Header */}
      <section className="page-hero sponsors-hero text-center">
        <div className="container">
          <p className="eyebrow reveal">Corporate &amp; Industry Collaboration</p>
          <h1 className="display-1 reveal-pop">
            Featured <span className="text-gold">Partners</span>
          </h1>
          <div className="section-divider reveal" style={{ margin: '20px auto 24px' }} />
          <p className="body-lg muted reveal" style={{ maxWidth: 680, margin: '0 auto' }}>
            Empowering architectural innovation in Quezon through strong collaborations
            with industry-leading material manufacturers, suppliers, and allied professional organizations.
          </p>

          {/* Filter Bar */}
          {!loading && items.length > 0 && (
            <div className="sponsors-filter-bar reveal" style={{ marginTop: 28 }}>
              <button
                type="button"
                className={`filter-pill-btn ${activeTier === 'all' ? 'active' : ''}`}
                onClick={() => setActiveTier('all')}
              >
                <span>All Partners</span>
                <span className="filter-pill-count">{items.length}</span>
              </button>
              <button
                type="button"
                className={`filter-pill-btn platinum-filter ${activeTier === 'platinum' ? 'active' : ''}`}
                onClick={() => setActiveTier('platinum')}
              >
                <IconSparkles size={13} className="platinum-star" />
                <span>Platinum Partners</span>
                <span className="filter-pill-count">{platinumCount}</span>
              </button>
              <button
                type="button"
                className={`filter-pill-btn ${activeTier === 'standard' ? 'active' : ''}`}
                onClick={() => setActiveTier('standard')}
              >
                <span>Industry Partners</span>
                <span className="filter-pill-count">{standardCount}</span>
              </button>
            </div>
          )}
        </div>
      </section>

      {/* Sponsors Section */}
      <section className="section sponsors-section">
        <div className="container">
          {error ? (
            <div className="dir-error reveal-pop">
              <p>Unable to load partners at this time. Please check back soon.</p>
            </div>
          ) : loading ? (
            <div className="grid-3 sponsors-grid">
              {[1, 2, 3].map((i) => (
                <div key={i} className="skeleton" style={{ height: 280, borderRadius: 'var(--r-lg)' }} />
              ))}
            </div>
          ) : filteredItems.length > 0 ? (
            <div className="grid-3 sponsors-grid reveal-stagger">
              {filteredItems.map((sponsor) => {
                const isPlatinum = sponsor.is_platinum === 1;
                const productCount = sponsor.products ? sponsor.products.length : 0;

                return (
                  <div
                    key={sponsor.id}
                    id={`sponsor-${sponsor.id}`}
                    className={`sponsor-card ${isPlatinum ? 'platinum-card' : ''}`}
                  >
                    <div className="sponsor-card-top">
                      <div className="sponsor-card-img-wrap">
                        {sponsor.logo_url && (
                          <img 
                            src={sponsor.logo_url} 
                            alt={sponsor.name} 
                            className="sponsor-card-img" 
                            onError={(e) => {
                              e.currentTarget.style.display = 'none';
                              if (e.currentTarget.nextElementSibling) {
                                e.currentTarget.nextElementSibling.style.display = 'flex';
                              }
                            }}
                          />
                        )}
                        <div 
                          className="sponsor-card-placeholder" 
                          style={{ display: sponsor.logo_url ? 'none' : 'flex' }}
                        >
                          {sponsor.name.slice(0, 2).toUpperCase()}
                        </div>
                      </div>
                      
                      {isPlatinum ? (
                        <span className="sponsor-tier-badge platinum-badge">
                          <IconSparkles size={12} />
                          Platinum Partner
                        </span>
                      ) : (
                        <span className="sponsor-tier-badge">
                          <IconStar size={12} />
                          Chapter Partner
                        </span>
                      )}
                    </div>

                    <div className="sponsor-card-body">
                      <h3 className="sponsor-card-title">{sponsor.name}</h3>
                      <p className="sponsor-card-desc">
                        {sponsor.description ||
                          'Official industry partner collaborating with UAP Quezon Chapter to promote excellence and quality construction.'}
                      </p>

                      {isPlatinum && productCount > 0 && (
                        <div className="platinum-products-pill">
                          <IconGrid size={13} />
                          <span>{productCount} Featured Product{productCount > 1 ? 's' : ''}</span>
                        </div>
                      )}
                    </div>

                    <div className="sponsor-card-footer">
                      <div className="sponsor-btn-group">
                        <Link
                          to={`/partners/${sponsor.id}`}
                          className={`btn ${isPlatinum ? 'btn-gold' : 'btn-outline'} sponsor-btn-details`}
                        >
                          <span>{isPlatinum ? 'View Details & Showcase' : 'View Details'}</span>
                          <span style={{ fontSize: '14px' }}>&rarr;</span>
                        </Link>

                        {sponsor.url && (
                          <a
                            href={sponsor.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="btn btn-ghost sponsor-btn-link"
                            title={`Visit official ${sponsor.name} website`}
                          >
                            <IconExternalLink size={15} />
                          </a>
                        )}
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          ) : (
            <div className="sponsors-empty reveal-pop text-center">
              <div className="sponsors-empty-icon">
                <IconHandshake size={56} />
              </div>
              <h2 className="heading-1">No Partners in this Category</h2>
              <p className="muted" style={{ maxWidth: 520, margin: '14px auto 28px' }}>
                {activeTier === 'platinum'
                  ? 'No Platinum Partners registered yet.'
                  : 'No industry partners found in this filter view.'}
              </p>
              <button
                type="button"
                onClick={() => setActiveTier('all')}
                className="btn btn-gold"
              >
                Show All Partners
              </button>
            </div>
          )}
        </div>
      </section>

      {/* Partnership Benefits */}
      <section className="section partner-benefits-section">
        <div className="container">
          <div className="section-header text-center reveal">
            <p className="eyebrow">Why Partner With Us</p>
            <h2 className="display-2">Partnership <span className="text-gold">Benefits</span></h2>
            <div className="section-divider" style={{ margin: '16px auto 0' }} />
          </div>

          <div className="grid-3 benefits-grid reveal-stagger" style={{ marginTop: '40px' }}>
            <div className="benefit-card">
              <div className="benefit-icon">
                <IconBuilding size={26} />
              </div>
              <h4 className="benefit-title">Architect Access</h4>
              <p className="benefit-desc">
                Direct engagement with licensed architects, design principals, and project leaders across Oriental and Occidental Quezon.
              </p>
            </div>

            <div className="benefit-card">
              <div className="benefit-icon">
                <IconDraftingCompass size={26} />
              </div>
              <h4 className="benefit-title">CPD &amp; Tech Seminars</h4>
              <p className="benefit-desc">
                Co-sponsor Continuing Professional Development (CPD) programs, technical product presentations, and design masterclasses.
              </p>
            </div>

            <div className="benefit-card">
              <div className="benefit-icon">
                <IconVerified size={26} />
              </div>
              <h4 className="benefit-title">Permanent Digital Visibility</h4>
              <p className="benefit-desc">
                Prominent brand placement across the chapter website, persistent sponsor rail, newsletters, and annual assembly materials.
              </p>
            </div>
          </div>

          {/* Become a Partner CTA */}
          <div className="partner-cta-box reveal-pop text-center" style={{ marginTop: '50px' }}>
            <h3 className="heading-1">Ready to Collaborate with Quezon Architects?</h3>
            <p className="muted" style={{ maxWidth: 540, margin: '12px auto 24px' }}>
              Connect with our Chapter Secretariat to receive our corporate sponsorship packages and event calendars.
            </p>
            <div className="flex-center" style={{ gap: '16px', flexWrap: 'wrap' }}>
              <Link to="/contact" className="btn btn-gold">
                Contact Chapter Secretariat
              </Link>
              <Link to="/directory" className="btn btn-ghost">
                View Architect Directory
              </Link>
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}
