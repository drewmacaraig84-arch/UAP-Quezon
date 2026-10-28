import { useState, useEffect, useRef, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import { IconStar, IconSparkles, IconHandshake, IconChevronDown, IconChevronUp, IconExternalLink } from './Icons';
import './SponsorDock.css';

/* ── Timing constants ── */
const DURATION_PLATINUM = 60;  // seconds total for a platinum sponsor (1 minute)
const DURATION_REGULAR  = 30;  // seconds for a regular sponsor
const DURATION_PRODUCT  = 5;   // seconds per product slide before repeating

export default function SponsorDock() {
  const { data: sponsors } = useApi('/api/sponsors.php');
  const [isCollapsed, setIsCollapsed]         = useState(false);
  const [isPaused, setIsPaused]               = useState(false);

  /* ── Spotlight State ── */
  const [activeSponsorIdx, setActiveSponsorIdx] = useState(0);
  const [activeProductIdx, setActiveProductIdx] = useState(0);
  const [sponsorProgress, setSponsorProgress]   = useState(1);   // 1→0
  const [productProgress, setProductProgress]   = useState(1);

  const sponsorTimerRef = useRef(null);
  const productTimerRef = useRef(null);
  const marqueeRef      = useRef(null);

  /* ── Sorted sponsor list ── */
  const rawItems = sponsors && Array.isArray(sponsors) ? sponsors : [];
  const sortedItems = [...rawItems].sort((a, b) => {
    const aP = a.is_platinum === 1 ? 1 : 0;
    const bP = b.is_platinum === 1 ? 1 : 0;
    if (bP !== aP) return bP - aP;
    return (a.display_order || 0) - (b.display_order || 0);
  });

  const totalSponsors = sortedItems.length;

  /* ── Active sponsor object ── */
  const activeSponsor = totalSponsors > 0 ? sortedItems[activeSponsorIdx % totalSponsors] : null;
  const isPlat        = activeSponsor?.is_platinum === 1;
  const products      = activeSponsor?.products && Array.isArray(activeSponsor.products) ? activeSponsor.products : [];
  const productCount  = products.length;

  const sponsorDuration = isPlat ? DURATION_PLATINUM : DURATION_REGULAR;

  /* ── Advance to next sponsor ── */
  const nextSponsor = useCallback(() => {
    setActiveSponsorIdx(prev => (prev + 1) % Math.max(totalSponsors, 1));
    setActiveProductIdx(0);
    setSponsorProgress(1);
    setProductProgress(1);
  }, [totalSponsors]);

  /* ── Advance to next product (wraps within sponsor) ── */
  const nextProduct = useCallback(() => {
    setActiveProductIdx(prev => {
      const next = (prev + 1) % Math.max(productCount, 1);
      return next;
    });
    setProductProgress(1);
  }, [productCount]);

  const prevProduct = useCallback(() => {
    setActiveProductIdx(prev => {
      const next = (prev - 1 + Math.max(productCount, 1)) % Math.max(productCount, 1);
      return next;
    });
    setProductProgress(1);
  }, [productCount]);

  /* ── Sponsor rotation timer ── */
  useEffect(() => {
    if (totalSponsors === 0 || isPaused) return;

    clearInterval(sponsorTimerRef.current);
    let elapsed = 0;
    setSponsorProgress(1);

    sponsorTimerRef.current = setInterval(() => {
      elapsed += 1;
      setSponsorProgress(1 - elapsed / sponsorDuration);
      if (elapsed >= sponsorDuration) {
        nextSponsor();
      }
    }, 1000);

    return () => clearInterval(sponsorTimerRef.current);
  }, [activeSponsorIdx, totalSponsors, sponsorDuration, isPaused, nextSponsor]);

  /* ── Product sub-rotation timer (repeats every DURATION_PRODUCT seconds) ── */
  useEffect(() => {
    clearInterval(productTimerRef.current);
    if (productCount <= 1 || isPaused) return;

    let elapsed = 0;
    setProductProgress(1);

    productTimerRef.current = setInterval(() => {
      elapsed += 1;
      setProductProgress(1 - elapsed / DURATION_PRODUCT);
      if (elapsed >= DURATION_PRODUCT) {
        nextProduct();
        elapsed = 0;
      }
    }, 1000);

    return () => clearInterval(productTimerRef.current);
  }, [activeSponsorIdx, activeProductIdx, productCount, isPaused, nextProduct]);

  /* ── Reset product index on sponsor change ── */
  useEffect(() => {
    setActiveProductIdx(0);
  }, [activeSponsorIdx]);

  /* ── Marquee duplicate items for mobile ── */
  const marqueeItems = sortedItems.length > 0
    ? (sortedItems.length < 5
        ? [...sortedItems, ...sortedItems, ...sortedItems, ...sortedItems]
        : [...sortedItems, ...sortedItems])
    : [];

  /* ── Active product ── */
  const activeProduct = products[activeProductIdx] || null;

  /* ── Render ── */
  return (
    <>
      {/* COLLAPSED FLOATING TRIGGER PILL (Mobile Only) */}
      {isCollapsed && (
        <button
          type="button"
          className="sponsor-collapsed-pill mobile-only reveal-pop"
          onClick={() => setIsCollapsed(false)}
          aria-label="Expand Featured Partners"
          title="Show Featured Partners"
        >
          <div className="sponsor-pill-inner">
            <span className="sponsor-pill-icon-wrap">
              <IconStar size={13} className="sponsor-pill-star" />
            </span>
            <span className="sponsor-pill-text">Featured Partners</span>
            {totalSponsors > 0 && <span className="sponsor-pill-badge">{totalSponsors}</span>}
            <span className="sponsor-pill-arrow"><IconChevronUp size={14} /></span>
          </div>
        </button>
      )}

      {/* EXPANDED SPONSOR DOCK / RAIL */}
      <aside
        className={`sponsor-rail${isCollapsed ? ' mobile-collapsed' : ''}`}
        aria-label="Featured Partners"
        onMouseEnter={() => setIsPaused(true)}
        onMouseLeave={() => setIsPaused(false)}
      >
        {/* Dock Header */}
        <div className="sponsor-rail-header">
          <Link to="/partners" className="sponsor-rail-title-wrap" title="View all Featured Partners">
            <IconStar size={14} className="sponsor-rail-star" />
            <span className="sponsor-rail-title">Featured Partners</span>
            {totalSponsors > 0 && <span className="sponsor-count-tag">{totalSponsors}</span>}
          </Link>

          <div className="sponsor-rail-actions">
            <Link to="/partners" className="sponsor-view-all-link" title="Explore all partners and sponsors">
              <span>View All</span>
              <span style={{ fontSize: '10px' }}>&rarr;</span>
            </Link>
            <button
              type="button"
              className="sponsor-toggle-btn mobile-only"
              onClick={() => setIsCollapsed(true)}
              aria-label="Minimize Partners"
              title="Minimize"
            >
              <IconChevronDown size={14} />
            </button>
          </div>
        </div>

        {/* ── DESKTOP: SINGLE-SPONSOR SPOTLIGHT ── */}
        <div className="sponsor-spotlight desktop-only">
          {activeSponsor ? (
            <>
              {/* Tier tag + sponsor nav */}
              <div className="spotlight-tier-row">
                <span className={`spotlight-tier-badge ${isPlat ? 'plat' : 'reg'}`}>
                  {isPlat ? <><IconSparkles size={10} />&nbsp;PLATINUM</> : 'FEATURED'}
                </span>
                {totalSponsors > 1 && (
                  <div className="spotlight-nav-dots">
                    {sortedItems.map((_, i) => (
                      <button
                        key={i}
                        type="button"
                        className={`spotlight-dot${i === activeSponsorIdx ? ' active' : ''}`}
                        onClick={() => {
                          setActiveSponsorIdx(i);
                          setActiveProductIdx(0);
                          setSponsorProgress(1);
                          setProductProgress(1);
                        }}
                        title={sortedItems[i].name}
                      />
                    ))}
                  </div>
                )}
              </div>

              {/* ── SPONSOR CARD: logo left + info right ── */}
              <div className="spotlight-partner-row">
                {/* Logo — left side */}
                <Link to={`/partners/${activeSponsor.id}`} className="spotlight-logo-wrap" title={`Visit ${activeSponsor.name}`}>
                  <div className="spotlight-logo-inner">
                    {activeSponsor.logo_url && (
                      <img 
                        src={activeSponsor.logo_url} 
                        alt={activeSponsor.name} 
                        className="spotlight-logo-img"
                        onError={(e) => {
                          e.currentTarget.style.display = 'none';
                          if (e.currentTarget.nextElementSibling) {
                            e.currentTarget.nextElementSibling.style.display = 'flex';
                          }
                        }}
                      />
                    )}
                    <span 
                      className="spotlight-logo-fallback" 
                      style={{ display: activeSponsor.logo_url ? 'none' : 'flex' }}
                    >
                      {activeSponsor.name.slice(0, 2).toUpperCase()}
                    </span>
                  </div>
                </Link>

                {/* Info — right side */}
                <div className="spotlight-info">
                  <Link to={`/partners/${activeSponsor.id}`} className="spotlight-name" title={activeSponsor.name}>
                    {activeSponsor.name}
                  </Link>
                  {activeSponsor.description && (
                    <p className="spotlight-desc">{activeSponsor.description}</p>
                  )}
                  {activeSponsor.url && (
                    <a href={activeSponsor.url} target="_blank" rel="noopener noreferrer" className="spotlight-ext-link">
                      <IconExternalLink size={10} />
                      <span>Visit Website</span>
                    </a>
                  )}
                </div>
              </div>

              {/* ── PRODUCT CAROUSEL ── product card fills remaining height ── */}
              {productCount > 0 && (
                <div className="spotlight-products-section">
                  <div className="spotlight-products-header">
                    <span className="spotlight-products-label">Products</span>
                    {productCount > 1 && (
                      <span className="spotlight-products-count">{activeProductIdx + 1} / {productCount}</span>
                    )}
                  </div>

                  {activeProduct && (
                    <div className="spotlight-product-card reveal-pop" key={`${activeSponsorIdx}-${activeProductIdx}`}>
                      <div className="spotlight-product-img-wrap">
                        {activeProduct.image_url && (
                          <img
                            src={activeProduct.image_url}
                            alt={activeProduct.name}
                            className="spotlight-product-img"
                            onError={(e) => {
                              e.currentTarget.style.display = 'none';
                              const fallback = e.currentTarget.parentElement.querySelector('.spotlight-product-fallback-wrap');
                              if (fallback) fallback.style.display = 'flex';
                            }}
                          />
                        )}
                        <div 
                          className="spotlight-product-fallback-wrap"
                          style={{ display: activeProduct.image_url ? 'none' : 'flex' }}
                        >
                          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--c-gold)', opacity: 0.7, marginBottom: 4 }}>
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                          </svg>
                          <span className="spotlight-product-fallback-badge">Catalog Material</span>
                        </div>
                      </div>
                      <div className="spotlight-product-body">
                        <span className="spotlight-product-name">{activeProduct.name}</span>
                        {activeProduct.description && (
                          <span className="spotlight-product-desc">{activeProduct.description}</span>
                        )}
                        {activeProduct.link_url && (
                          <a
                            href={activeProduct.link_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="spotlight-product-link"
                          >
                            <IconExternalLink size={10} />
                            <span>Learn More</span>
                          </a>
                        )}
                      </div>
                    </div>
                  )}
                </div>
              )}

              {/* ── NAV: pinned at the very bottom of the panel ── */}
              {productCount > 0 && (
                <div className="spotlight-product-nav">
                  <button
                    type="button"
                    className="spotlight-product-nav-btn"
                    onClick={prevProduct}
                    disabled={productCount <= 1}
                    title="Previous product"
                  >‹</button>

                  <div className="spotlight-product-dots">
                    {products.map((_, i) => (
                      <button
                        key={i}
                        type="button"
                        className={`spotlight-product-dot${i === activeProductIdx ? ' active' : ''}`}
                        onClick={() => { setActiveProductIdx(i); setProductProgress(1); }}
                        title={products[i].name}
                      />
                    ))}
                  </div>

                  <button
                    type="button"
                    className="spotlight-product-nav-btn"
                    onClick={nextProduct}
                    disabled={productCount <= 1}
                    title="Next product"
                  >›</button>
                </div>
              )}
            </>
          ) : (
            /* Empty state */
            <Link to="/partners" className="sponsor-rail-empty-link" title="Partner with UAP Quezon Chapter">
              <div className="sponsor-rail-empty-icon"><IconHandshake size={28} /></div>
              <span className="sponsor-rail-empty-text">Partner with Us</span>
              <span className="sponsor-rail-empty-sub">Showcase your brand on our official chapter portal</span>
            </Link>
          )}
        </div>

        {/* ── MOBILE: AUTO-SLIDE MARQUEE TICKER ── */}
        <div
          className="sponsor-mobile-ticker-wrap mobile-only"
          onMouseEnter={() => setIsPaused(true)}
          onMouseLeave={() => setIsPaused(false)}
          onTouchStart={() => setIsPaused(true)}
          onTouchEnd={() => setIsPaused(false)}
        >
          {marqueeItems.length > 0 ? (
            <div
              ref={marqueeRef}
              className={`sponsor-mobile-marquee-track${isPaused ? ' paused' : ''}`}
            >
              {marqueeItems.map((sponsor, idx) => {
                const sp = sponsor.is_platinum === 1;
                return (
                  <Link
                    key={`mob-${sponsor.id}-${idx}`}
                    to={`/partners/${sponsor.id}`}
                    className={`sponsor-ticker-card ${sp ? 'is-platinum-ticker' : ''}`}
                    title={`View ${sponsor.name}`}
                  >
                    <div className="sponsor-ticker-img-wrap">
                      {sponsor.logo_url && (
                        <img 
                          src={sponsor.logo_url} 
                          alt={sponsor.name} 
                          className="sponsor-ticker-img" 
                          onError={(e) => {
                            e.currentTarget.style.display = 'none';
                            if (e.currentTarget.nextElementSibling) {
                              e.currentTarget.nextElementSibling.style.display = 'flex';
                            }
                          }}
                        />
                      )}
                      <div 
                        className="sponsor-ticker-placeholder" 
                        style={{ display: sponsor.logo_url ? 'none' : 'flex' }}
                      >
                        {sponsor.name.slice(0, 2).toUpperCase()}
                      </div>
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column', minWidth: 0 }}>
                      <span className="sponsor-ticker-name">{sponsor.name}</span>
                      {sp && <span style={{ fontSize: '8.5px', color: '#f5b800', fontWeight: 800 }}>★ PLATINUM</span>}
                    </div>
                  </Link>
                );
              })}
            </div>
          ) : (
            <div className="sponsor-mobile-empty">
              <Link to="/partners" className="sponsor-mobile-empty-link">
                <IconHandshake size={16} />
                <span>Partner with UAP Quezon &bull; Inquire for Partnership</span>
              </Link>
            </div>
          )}
        </div>
      </aside>
    </>
  );
}
