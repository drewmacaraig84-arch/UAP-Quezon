import { useState, useEffect, lazy, Suspense } from 'react';
import { Link } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import ArchitectCard from '../components/ArchitectCard';
import NewsCard from '../components/NewsCard';
import AutoCarousel from '../components/AutoCarousel';
import { IconBuilding, IconLeaf, IconHandshake } from '../components/Icons';
import './Home.css';
import './News.css';

const HeroCanvas = lazy(() => import('../components/HeroCanvas'));

export default function Home() {
  const { data: settings } = useApi('/api/settings.php');
  const { data: members, loading: mLoad } = useApi('/api/members.php');
  const { data: news, loading: nLoad } = useApi('/api/news.php');
  const { data: homeImages } = useApi('/api/home_images.php');
  const [selectedNews, setSelectedNews] = useState(null);

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') setSelectedNews(null);
    };
    if (selectedNews) {
      window.addEventListener('keydown', handleKeyDown);
      document.body.style.overflow = 'hidden';
    }
    return () => {
      window.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = '';
    };
  }, [selectedNews]);

  const selectedDate = selectedNews?.date_posted
    ? new Date(selectedNews.date_posted).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      })
    : '';

  const featured = members || [];
  const latestNews = news || [];

  return (
    <main>
      {/* ========== HERO ========== */}
      <section className="hero-section">
        <Suspense fallback={null}>
          <HeroCanvas />
        </Suspense>

        <div className="container-wide hero-container">
          <div className="hero-content">
            <p className="eyebrow hero-eyebrow animate-fade-in-up">
              United Architects of the Philippines — Quezon Province Chapter
            </p>
            <h1 className="display-1 hero-title animate-fade-in-up">
              Building the Future<br />
              <span className="text-gold">of Quezon</span>
            </h1>
            <p className="hero-sub body-lg muted animate-fade-in-up">
              {settings?.about_us
                ? settings.about_us.slice(0, 180) + (settings.about_us.length > 180 ? '...' : '')
                : 'Promoting architectural excellence, professional integrity, and sustainable development across Quezon Province.'}
            </p>
            <div className="hero-actions animate-fade-in-up">
              <Link to="/directory" className="btn btn-gold">
                View Architect Directory
              </Link>
              <Link to="/about" className="btn btn-outline">
                About the Chapter
              </Link>
            </div>
          </div>
        </div>

        <div className="hero-scroll-hint">
          <span />
        </div>
      </section>

      {/* ========== SHOWCASE SLIDER STRIP ========== */}
      {homeImages && homeImages.length > 0 && (
        <section className="showcase-strip">
          <div className="home-showcase-marquee">
            <div className="home-showcase-track">
              {/* Duplicate for smooth continuous infinite loop */}
              {[...homeImages, ...homeImages].map((src, i) => (
                <div key={i} className="home-showcase-card">
                  <img
                    src={src}
                    alt={`UAP Quezon activity ${i + 1}`}
                    loading="lazy"
                  />
                </div>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* ========== ABOUT MISSION ========== */}
      <section className="section about-section">
        <div className="container about-grid">
          <div className="about-visual reveal-left">
            <div className="about-logo-wrap">
              <img 
                src={settings?.logo || '/public/logo.jpg'} 
                alt={settings?.org_name || 'UAP Quezon Province Chapter'} 
                onError={(e) => {
                  if (!e.currentTarget.src.endsWith('/logo.jpg')) {
                    e.currentTarget.src = '/public/logo.jpg';
                  }
                }}
              />
            </div>
            <div className="about-accent-ring" />
          </div>
          <div className="about-content reveal-right">
            <p className="eyebrow">Our Mission</p>
            <h2 className="display-2">Advocates of<br /><span className="text-gold">Architectural Excellence</span></h2>
            <div className="section-divider" />
            <p className="body-lg muted" style={{ marginTop: '20px' }}>
              {settings?.about_us || 'The UAP Quezon Province Chapter brings together registered architects dedicated to advancing the profession, upholding ethical standards, and serving the communities of Quezon through design excellence and innovation.'}
            </p>
            <div className="about-features reveal-stagger">
              {[{
                icon: <IconBuilding size={22} />,
                label: 'Professional Standards',
                desc: 'Upholding UAP ethics and PRC regulations',
              }, {
                icon: <IconLeaf size={22} />,
                label: 'Sustainable Design',
                desc: 'Advocating green and resilient architecture',
              }, {
                icon: <IconHandshake size={22} />,
                label: 'Community Service',
                desc: 'Serving Quezon through pro-bono initiatives',
              }].map((f) => (
                <div key={f.label} className="about-feature">
                  <span className="about-feature-icon">{f.icon}</span>
                  <div>
                    <strong>{f.label}</strong>
                    <p>{f.desc}</p>
                  </div>
                </div>
              ))}
            </div>
            <Link to="/about" className="btn btn-outline" style={{ marginTop: '24px', alignSelf: 'flex-start' }}>
              Learn More About Us
            </Link>
          </div>
        </div>
      </section>

      {/* ========== FEATURED ARCHITECTS ========== */}
      <section className="section featured-section">
        <div className="container">
          <div className="section-header text-center reveal-pop">
            <p className="eyebrow">Chapter Architects</p>
            <h2 className="display-2">Featured <span className="text-gold">Directory</span></h2>
            <div className="section-divider" style={{ margin: '16px auto 0' }} />
          </div>

          {mLoad ? (
            <div className="grid-3 skeleton-grid">
              {[1,2,3].map(i => <div key={i} className="skeleton" style={{ height: 340, borderRadius: 'var(--r-lg)' }} />)}
            </div>
          ) : featured.length > 0 ? (
            <AutoCarousel
              items={featured}
              renderItem={(m) => <ArchitectCard member={m} />}
              cardMinWidth={280}
              gap={24}
              autoSlideInterval={3000}
              emptyText="No architects in the directory yet. Check back soon."
            />
          ) : (
            <div className="empty-state reveal-pop">
              <p className="muted">No architects in the directory yet. Check back soon.</p>
            </div>
          )}

          <div className="text-center reveal-pop" style={{ marginTop: '24px' }}>
            <Link to="/directory" className="btn btn-ghost">
              View All Architects →
            </Link>
          </div>
        </div>
      </section>

      {/* ========== NEWS ========== */}
      <section className="section news-section">
        <div className="container">
          <div className="section-header flex-between reveal-left">
            <div>
              <p className="eyebrow">Latest Updates</p>
              <h2 className="heading-1">News &amp; <span className="text-gold">Announcements</span></h2>
            </div>
            <Link to="/news" className="btn btn-outline">All News →</Link>
          </div>

          {nLoad ? (
            <div className="grid-3">
              {[1,2,3].map(i => <div key={i} className="skeleton" style={{ height: 200, borderRadius: 'var(--r-lg)' }} />)}
            </div>
          ) : latestNews.length > 0 ? (
            <AutoCarousel
              items={latestNews}
              renderItem={(n) => <NewsCard item={n} onClick={() => setSelectedNews(n)} />}
              cardMinWidth={320}
              gap={24}
              autoSlideInterval={3000}
              emptyText="No announcements at this time."
            />
          ) : (
            <div className="empty-state reveal-pop">
              <p className="muted">No announcements at this time.</p>
            </div>
          )}
        </div>
      </section>

      {/* FULL ANNOUNCEMENT MODAL */}
      {selectedNews && (
        <div className="news-modal-backdrop" onClick={() => setSelectedNews(null)}>
          <div
            className="news-modal-card card"
            onClick={(e) => e.stopPropagation()}
            role="dialog"
            aria-modal="true"
            aria-labelledby="home-news-modal-heading"
          >
            <button
              type="button"
              className="news-modal-close"
              onClick={() => setSelectedNews(null)}
              aria-label="Close modal"
            >
              &times;
            </button>

            <div className="news-modal-header">
              <span className="news-modal-date">{selectedDate}</span>
              <h2 id="home-news-modal-heading" className="news-modal-title">
                {selectedNews.title}
              </h2>
            </div>

            <div className="news-modal-divider" />

            <div className="news-modal-content">
              {selectedNews.summary.split('\n').map((paragraph, index) => (
                paragraph.trim() ? <p key={index}>{paragraph}</p> : <br key={index} />
              ))}
            </div>

            <div className="news-modal-footer">
              <button
                type="button"
                className="btn btn-outline"
                onClick={() => setSelectedNews(null)}
              >
                Close Announcement
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========== CTA BANNER ========== */}
      <section className="cta-section">
        <div className="container text-center reveal-pop">
          <p className="eyebrow">Are you a UAP Quezon Member?</p>
          <h2 className="display-2">Access Your <span className="text-gold">Member Portal</span></h2>
          <p className="body-lg muted" style={{ maxWidth: 520, margin: '16px auto 32px' }}>
            Track your dues, upload payment proofs, generate official receipts, and apply to join the architect directory.
          </p>
          <div className="flex-center" style={{ gap: '16px', flexWrap: 'wrap' }}>
            <a href="/auth/login.php" className="btn btn-gold">
              Sign In to Portal
            </a>
            <a href="/auth/register.php" className="btn btn-ghost">
              Register as Member
            </a>
          </div>
        </div>
      </section>
    </main>
  );
}
