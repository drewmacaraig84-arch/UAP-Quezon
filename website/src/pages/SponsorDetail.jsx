import { useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import {
  IconStar,
  IconSparkles,
  IconExternalLink,
  IconBuilding,
  IconArrowLeft,
  IconGrid,
  IconDraftingCompass,
  IconVerified
} from '../components/Icons';
import './SponsorDetail.css';

export default function SponsorDetail() {
  const { id } = useParams();
  const { data: sponsor, loading, error } = useApi(`/api/sponsors.php?id=${id}`);

  // Scroll to top on page load
  useEffect(() => {
    window.scrollTo(0, 0);
  }, [id]);

  if (loading) {
    return (
      <main className="page-container sponsor-detail-page">
        <div className="container" style={{ padding: '80px 20px' }}>
          <div className="skeleton" style={{ height: 40, width: 200, marginBottom: 24, borderRadius: 8 }} />
          <div className="skeleton" style={{ height: 320, borderRadius: 16, marginBottom: 40 }} />
          <div className="skeleton" style={{ height: 260, borderRadius: 16 }} />
        </div>
      </main>
    );
  }

  if (error || !sponsor) {
    return (
      <main className="page-container sponsor-detail-page">
        <div className="container text-center" style={{ padding: '100px 20px' }}>
          <div className="sponsor-detail-not-found reveal-pop">
            <h1 className="display-2" style={{ marginBottom: 16 }}>Partner Not Found</h1>
            <p className="muted" style={{ maxWidth: 500, margin: '0 auto 28px' }}>
              The sponsor or partner organization you are looking for is currently unavailable or inactive.
            </p>
            <Link to="/partners" className="btn btn-gold">
              <IconArrowLeft size={16} />
              <span>Back to Featured Partners</span>
            </Link>
          </div>
        </div>
      </main>
    );
  }

  const isPlatinum = sponsor.is_platinum === 1;
  const products = sponsor.products && Array.isArray(sponsor.products) ? sponsor.products : [];

  return (
    <main className="page-container sponsor-detail-page">
      {/* Top Breadcrumb Navigation */}
      <section className="sponsor-detail-nav-sec">
        <div className="container">
          <Link to="/partners" className="sponsor-back-breadcrumb">
            <IconArrowLeft size={14} />
            <span>Back to Featured Partners</span>
          </Link>
        </div>
      </section>

      {/* Main Sponsor Profile Hero */}
      <section className="sponsor-detail-hero-sec">
        <div className="container">
          <div className={`sponsor-detail-hero-card ${isPlatinum ? 'is-platinum-card' : ''}`}>
            <div className="sponsor-detail-logo-wrap">
              {sponsor.logo_url && (
                <img
                  src={sponsor.logo_url}
                  alt={sponsor.name}
                  className="sponsor-detail-logo-img"
                  onError={(e) => {
                    e.currentTarget.style.display = 'none';
                    if (e.currentTarget.nextElementSibling) {
                      e.currentTarget.nextElementSibling.style.display = 'flex';
                    }
                  }}
                />
              )}
              <div 
                className="sponsor-detail-logo-placeholder"
                style={{ display: sponsor.logo_url ? 'none' : 'flex' }}
              >
                {sponsor.name.slice(0, 2).toUpperCase()}
              </div>
            </div>

            <div className="sponsor-detail-hero-info">
              <div className="sponsor-detail-badge-row">
                {isPlatinum ? (
                  <span className="sponsor-tier-badge platinum-badge">
                    <IconSparkles size={13} />
                    <span>Official Platinum Partner</span>
                  </span>
                ) : (
                  <span className="sponsor-tier-badge">
                    <IconStar size={13} />
                    <span>Chapter Industry Partner</span>
                  </span>
                )}
              </div>

              <h1 className="sponsor-detail-title">{sponsor.name}</h1>

              <p className="sponsor-detail-bio">
                {sponsor.description ||
                  `${sponsor.name} is an esteemed corporate partner collaborating with the United Architects of the Philippines Quezon Chapter, supporting architectural practice, quality construction, and regional development.`}
              </p>

              <div className="sponsor-detail-cta-row">
                {sponsor.url && (
                  <a
                    href={sponsor.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="btn btn-gold sponsor-hero-btn"
                  >
                    <span>Visit Official Website</span>
                    <IconExternalLink size={15} />
                  </a>
                )}
                <Link to="/contact" className="btn btn-outline sponsor-hero-btn">
                  <span>Connect via Secretariat</span>
                </Link>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Platinum Promotional Products Showcase */}
      {isPlatinum && (
        <section className="section sponsor-products-section">
          <div className="container">
            <div className="section-header text-center" style={{ marginBottom: 40 }}>
              <p className="eyebrow" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                <IconDraftingCompass size={14} />
                <span>Specifier &amp; Material Showcase</span>
              </p>
              <h2 className="display-2">
                Promotional <span className="text-gold">Products &amp; Catalogs</span>
              </h2>
              <div className="section-divider" style={{ margin: '16px auto 0' }} />
              <p className="muted" style={{ maxWidth: 640, margin: '14px auto 0' }}>
                Featured building systems, architectural finishes, and technical product catalogs recommended for professional specifications.
              </p>
            </div>

            {products.length > 0 ? (
              <div className="grid-3 sponsor-products-grid">
                {products.map((prod, idx) => (
                  <div key={prod.id || idx} className="sponsor-product-card">
                    <div className="sponsor-product-img-wrap">
                      {prod.image_url && (
                        <img
                          src={prod.image_url}
                          alt={prod.name}
                          className="sponsor-product-img"
                          onError={(e) => {
                            e.currentTarget.style.display = 'none';
                            const fallback = e.currentTarget.parentElement.querySelector('.sponsor-product-fallback-wrap');
                            if (fallback) fallback.style.display = 'flex';
                          }}
                        />
                      )}
                      <div 
                        className="sponsor-product-fallback-wrap"
                        style={{ display: prod.image_url ? 'none' : 'flex' }}
                      >
                        <IconGrid size={28} style={{ color: 'var(--c-gold)', opacity: 0.7, marginBottom: 4 }} />
                        <span className="sponsor-product-fallback-badge">Catalog Specification</span>
                      </div>
                    </div>

                    <div className="sponsor-product-body">
                      <h3 className="sponsor-product-name">{prod.name}</h3>
                      {prod.description && (
                        <p className="sponsor-product-desc">{prod.description}</p>
                      )}

                      {prod.link_url && (
                        <div className="sponsor-product-footer">
                          <a
                            href={prod.link_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="btn btn-sm btn-outline sponsor-product-link-btn"
                          >
                            <span>View Product / Catalog</span>
                            <IconExternalLink size={13} />
                          </a>
                        </div>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="sponsor-no-products-card text-center">
                <IconGrid size={40} className="muted" style={{ margin: '0 auto 12px' }} />
                <h3 className="heading-1">Promotional Products Coming Soon</h3>
                <p className="muted" style={{ maxWidth: 460, margin: '8px auto 20px' }}>
                  {sponsor.name} product catalogs and material specifications are currently being updated by the chapter.
                </p>
                {sponsor.url && (
                  <a
                    href={sponsor.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="btn btn-gold"
                  >
                    <span>Browse Official Catalog</span>
                    <IconExternalLink size={14} />
                  </a>
                )}
              </div>
            )}
          </div>
        </section>
      )}

      {/* Chapter Collaboration Info */}
      <section className="section sponsor-collab-section">
        <div className="container">
          <div className="sponsor-collab-box">
            <div className="sponsor-collab-icon">
              <IconVerified size={32} />
            </div>
            <div>
              <h3 className="heading-1" style={{ marginBottom: 6 }}>
                Architectural Partnerships with UAP Quezon
              </h3>
              <p className="muted" style={{ fontSize: '0.92rem', lineHeight: 1.6, margin: 0 }}>
                Corporate sponsors and industry partners work closely with the chapter to host technical seminars, CPD product masterclasses, and promote sustainable construction standards throughout Quezon Province.
              </p>
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}
