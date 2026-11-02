import { useState, useEffect, lazy, Suspense } from 'react';
import { Link } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import ArchitectCard from '../components/ArchitectCard';
import NewsCard from '../components/NewsCard';
import AutoCarousel from '../components/AutoCarousel';
import {
  IconBuilding,
  IconLeaf,
  IconHandshake,
  IconMapPin,
  IconMail,
  IconPhone,
  IconMailSent,
  IconVerified,
  IconStar
} from '../components/Icons';
import './Home.css';
import './News.css';

const HeroCanvas = lazy(() => import('../components/HeroCanvas'));

const DEFAULT_MILESTONES = [
  {
    year: '1977',
    title: 'Chartering of UAP Quezon Province Chapter',
    description: 'Chartered on November 11, 1977 as the first chapter in District B4 and the 18th chapter registered in the Philippines, founded under Benecio N. Tolentino, FUAP.'
  },
  {
    year: '1995',
    title: 'Preserving Quezon Heritage & Culture',
    description: 'Pioneered heritage documentation and preservation advocacy for ancestral stone houses and civic landmarks across Lucena City, Tayabas, and Sariaya.'
  },
  {
    year: '2004',
    title: 'Advocacy for Republic Act No. 9266',
    description: 'Led active collaboration with local municipal governments and building officials across Quezon Province for the full implementation of the Architecture Act.'
  },
  {
    year: '2018',
    title: 'Guhit Arkitektong Quezonian',
    description: "Launched the chapter's signature annual design exhibition, CPD symposium, and platform uniting local architectural talent and creative community dialogue."
  },
  {
    year: '2026',
    title: 'Approaching Golden 50th Anniversary',
    description: 'Comprising over 300 members and guided by 27 Chapter Presidents, the chapter launches its digital member portal and verified architect directory.'
  }
];

export default function Home() {
  const { data: settings } = useApi('/api/settings.php');
  const { data: members, loading: mLoad } = useApi('/api/members.php');
  const { data: news, loading: nLoad } = useApi('/api/news.php');
  const { data: homeImages } = useApi('/api/home_images.php');
  const { data: milestonesData } = useApi('/api/milestones.php');

  // Logo fallback state
  const [logoError, setLogoError] = useState(false);
  const logoSrc = logoError ? '/logo.jpg' : (settings?.logo || '/public/logo.jpg');

  // News Modal State
  const [selectedNews, setSelectedNews] = useState(null);

  // In-Page Contact Form State
  const [contactForm, setContactForm] = useState({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: ''
  });
  const [contactSubmitting, setContactSubmitting] = useState(false);
  const [contactSent, setContactSent] = useState(false);
  const [contactError, setContactError] = useState('');

  // Close modal with Escape key and lock body scroll
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

  // Safe date parser to prevent RangeError: Invalid time value
  let selectedDate = '';
  if (selectedNews?.date_posted) {
    try {
      const parsed = new Date(selectedNews.date_posted);
      selectedDate = !isNaN(parsed.getTime())
        ? parsed.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
        : String(selectedNews.date_posted);
    } catch {
      selectedDate = String(selectedNews.date_posted);
    }
  }

  const featured = Array.isArray(members) ? members : [];
  const latestNews = Array.isArray(news) ? news : [];
  const milestonesList = Array.isArray(milestonesData) && milestonesData.length > 0 ? milestonesData : DEFAULT_MILESTONES;
  const imageList = Array.isArray(homeImages) ? homeImages : [];

  const contactEmail = settings?.contact_email || 'quezon@united-architects.ph';
  const contactAddress = settings?.contact_address || 'Lucena City, Quezon Province, Philippines';
  const contactPhone = settings?.contact_phone || '+63 917 555 UAPQ';

  const handleContactChange = (e) => {
    setContactError('');
    setContactForm({ ...contactForm, [e.target.name]: e.target.value });
  };

  const handleContactSubmit = async (e) => {
    e.preventDefault();
    setContactError('');

    if (!contactForm.name.trim()) {
      setContactError('Please enter your full name.');
      return;
    }

    if (!contactForm.email.trim() && !contactForm.phone.trim()) {
      setContactError('Please enter at least an email address or mobile phone number.');
      return;
    }

    if (contactForm.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contactForm.email.trim())) {
      setContactError('Please provide a valid email address.');
      return;
    }

    if (!contactForm.message.trim()) {
      setContactError('Please write your message or inquiry.');
      return;
    }

    setContactSubmitting(true);

    try {
      const res = await fetch('/api/contact.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(contactForm)
      });
      const data = await res.json();

      if (res.ok && data.success) {
        setContactSent(true);
        setContactForm({ name: '', email: '', phone: '', subject: '', message: '' });
      } else {
        setContactError(data.error || 'Failed to submit inquiry. Please try again.');
      }
    } catch {
      setContactError('Unable to reach the server. Please check your internet connection.');
    } finally {
      setContactSubmitting(false);
    }
  };

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
              Chartered November 11, 1977. Uniting over 300 registered architects dedicated to architectural excellence, heritage preservation, and sustainable community advancement.
            </p>
            <div className="hero-actions animate-fade-in-up">
              <Link to="/directory" className="btn btn-gold">
                View Architects Directory
              </Link>
              <Link to="/about" className="btn btn-outline">
                About the Chapter
              </Link>
              <Link to="/contact" className="btn btn-ghost" style={{ padding: '0 12px' }}>
                Contact Secretariat →
              </Link>
            </div>
          </div>
        </div>

        <div className="hero-scroll-hint">
          <span />
        </div>
      </section>

      {/* ========== CHAPTER QUICK STATS STRIP ========== */}
      <section className="chapter-stats-strip">
        <div className="container">
          <div className="chapter-stats-grid reveal-pop">
            <div className="stat-card">
              <span className="stat-num text-gold">1977</span>
              <span className="stat-label">Chartered Year</span>
              <span className="stat-desc">1st in District B4 • 18th in PH</span>
            </div>
            <div className="stat-card">
              <span className="stat-num text-gold">27</span>
              <span className="stat-label">Chapter Presidents</span>
              <span className="stat-desc">Guiding a legacy of leadership</span>
            </div>
            <div className="stat-card">
              <span className="stat-num text-gold">300+</span>
              <span className="stat-label">Registered Architects</span>
              <span className="stat-desc">Across all Quezon districts</span>
            </div>
            <div className="stat-card">
              <span className="stat-num text-gold">50th</span>
              <span className="stat-label">Golden Anniversary</span>
              <span className="stat-desc">Approaching milestone celebration</span>
            </div>
          </div>
        </div>
      </section>

      {/* ========== SHOWCASE SLIDER STRIP ========== */}
      {imageList.length > 0 && (
        <section className="showcase-strip">
          <div className="home-showcase-marquee">
            <div className="home-showcase-track">
              {[...imageList, ...imageList].map((src, i) => (
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

      {/* =========================================================
          1. ABOUT SUMMARY SECTION
          ========================================================= */}
      <section className="section home-section about-section" id="about-summary">
        <div className="container about-grid">
          <div className="about-visual reveal-left">
            <div className="about-logo-wrap">
              <img 
                src={logoSrc} 
                alt={settings?.org_name || 'UAP Quezon Province Chapter'} 
                onError={() => {
                  if (!logoError) setLogoError(true);
                }}
              />
            </div>
            <div className="about-accent-ring" />
          </div>

          <div className="about-content reveal-right">
            <div className="section-badge-pill">
              <span className="badge-dot" /> Page Summary • About UAP Quezon
            </div>
            <h2 className="display-2">
              Built Through Time &amp;<br />
              <span className="text-gold">Advocates of Excellence</span>
            </h2>
            <div className="section-divider" />
            <p className="body-lg muted" style={{ marginTop: '16px' }}>
              The United Architects of the Philippines – Quezon Province Chapter holds a proud place in national architectural history. Chartered on November 11, 1977 as the first chapter in District B4 and 18th in the country, under Founding Chapter President Ar. Benecio N. Tolentino, FUAP.
            </p>
            <p className="body-sm muted">
              From our inception, we have united architects across Quezon Province, cultivating professional ethics, creative design, and active community service in partnership with local municipalities.
            </p>

            <div className="about-features reveal-stagger">
              {[
                {
                  icon: <IconBuilding size={22} />,
                  label: 'Professional Integrity & RA 9266',
                  desc: 'Enforcing the Architecture Act and protecting professional standards.',
                },
                {
                  icon: <IconLeaf size={22} />,
                  label: 'Sustainable & Resilient Design',
                  desc: 'Harmonizing architecture with Mt. Banahaw and Quezon’s coastline.',
                },
                {
                  icon: <IconHandshake size={22} />,
                  label: 'Community Extension Services',
                  desc: 'Pro-bono civic planning, outreach, and local government collaboration.',
                }
              ].map((f) => (
                <div key={f.label} className="about-feature">
                  <span className="about-feature-icon">{f.icon}</span>
                  <div>
                    <strong>{f.label}</strong>
                    <p>{f.desc}</p>
                  </div>
                </div>
              ))}
            </div>

            <div className="section-summary-actions">
              <Link to="/about" className="btn btn-gold">
                Read Full Chapter Story →
              </Link>
              <span className="summary-page-tag">Explores: History, Mission, Values, Leadership</span>
            </div>
          </div>
        </div>
      </section>

      {/* =========================================================
          2. ARCHITECTS DIRECTORY SUMMARY SECTION
          ========================================================= */}
      <section className="section home-section featured-section" id="architects-summary">
        <div className="container">
          <div className="section-header text-center reveal-pop">
            <div className="section-badge-pill" style={{ margin: '0 auto 12px' }}>
              <span className="badge-dot" /> Page Summary • Arkitektong Quezonian
            </div>
            <h2 className="display-2">
              Chapter <span className="text-gold">Architects Directory</span>
            </h2>
            <p className="body-lg muted" style={{ maxWidth: 640, margin: '14px auto 0' }}>
              Connect with certified, PRC-registered architects across Lucena City, Tayabas, Sariaya, and the four legislative districts of Quezon Province.
            </p>
            <div className="section-divider" style={{ margin: '16px auto 0' }} />

            {/* Quick Specialty Badges */}
            <div className="specialty-pills-row">
              {['Residential Architecture', 'Commercial & Retail', 'Heritage Conservation', 'Sustainable Design', 'Interior Architecture'].map((spec) => (
                <span key={spec} className="spec-pill">
                  <IconVerified size={14} style={{ color: 'var(--c-gold)' }} /> {spec}
                </span>
              ))}
            </div>
          </div>

          {mLoad ? (
            <div className="grid-3 skeleton-grid">
              {[1, 2, 3].map((i) => (
                <div key={i} className="skeleton" style={{ height: 340, borderRadius: 'var(--r-lg)' }} />
              ))}
            </div>
          ) : featured.length > 0 ? (
            <AutoCarousel
              items={featured}
              renderItem={(m) => <ArchitectCard member={m} />}
              cardMinWidth={280}
              gap={24}
              autoSlideInterval={3200}
              emptyText="No architects in the directory yet. Check back soon."
            />
          ) : (
            <div className="empty-state reveal-pop">
              <p className="muted">No architects in the directory yet. Check back soon.</p>
            </div>
          )}

          <div className="section-summary-footer text-center reveal-pop">
            <div className="summary-cta-box card">
              <div>
                <h4 style={{ color: 'var(--c-white)', marginBottom: 4 }}>Looking for a licensed architect in Quezon?</h4>
                <p className="muted body-sm">Search by specialization, location, firm name, and verified PRC credentials.</p>
              </div>
              <Link to="/architects" className="btn btn-gold">
                Browse Complete Architect Directory →
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* =========================================================
          3. NEWS & EVENTS SUMMARY SECTION
          ========================================================= */}
      <section className="section home-section news-section" id="news-summary">
        <div className="container">
          <div className="section-header flex-between reveal-left">
            <div>
              <div className="section-badge-pill">
                <span className="badge-dot" /> Page Summary • Chapter Updates
              </div>
              <h2 className="heading-1">
                News, CPD &amp; <span className="text-gold">Events</span>
              </h2>
              <p className="muted body-sm" style={{ marginTop: 6, maxWidth: 540 }}>
                CPD accredited masterclasses, signature gatherings like <em>Guhit Arkitektong Quezonian</em>, and community outreach.
              </p>
            </div>
            <Link to="/news" className="btn btn-outline">
              View All News &amp; Events →
            </Link>
          </div>

          {nLoad ? (
            <div className="grid-3" style={{ marginTop: 24 }}>
              {[1, 2, 3].map((i) => (
                <div key={i} className="skeleton" style={{ height: 200, borderRadius: 'var(--r-lg)' }} />
              ))}
            </div>
          ) : latestNews.length > 0 ? (
            <AutoCarousel
              items={latestNews}
              renderItem={(n) => <NewsCard item={n} onClick={() => setSelectedNews(n)} />}
              cardMinWidth={320}
              gap={24}
              autoSlideInterval={3400}
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
              {(selectedNews?.summary || '').split('\n').map((paragraph, index) => (
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

      {/* =========================================================
          4. CHAPTER MILESTONES SUMMARY SECTION
          ========================================================= */}
      <section className="section home-section milestones-summary-section" id="milestones-summary">
        <div className="container">
          <div className="section-header text-center reveal-pop">
            <div className="section-badge-pill" style={{ margin: '0 auto 12px' }}>
              <span className="badge-dot" /> Page Summary • Legacy of Excellence
            </div>
            <h2 className="display-2">
              Chapter <span className="text-gold">Milestones &amp; Journey</span>
            </h2>
            <p className="body-lg muted" style={{ maxWidth: 660, margin: '14px auto 0' }}>
              Guiding our path as we approach our Golden 50th Anniversary — 27 Chapter Presidents, over 300 members, and five decades of nation-building through architecture.
            </p>
            <div className="section-divider" style={{ margin: '16px auto 0' }} />
          </div>

          <div className="home-milestones-grid reveal-stagger">
            {milestonesList.slice(0, 5).map((ms, idx) => (
              <div key={ms.id ?? idx} className="home-milestone-card card">
                <div className="milestone-card-top">
                  <span className="milestone-year-pill">{ms.year}</span>
                  <span className="milestone-step-index">0{idx + 1}</span>
                </div>
                <h3 className="milestone-card-title">{ms.title}</h3>
                <p className="milestone-card-desc muted">{ms.description || ms.content}</p>
                <div className="milestone-bottom-glow" />
              </div>
            ))}
          </div>

          <div className="section-summary-footer text-center reveal-pop" style={{ marginTop: 36 }}>
            <div className="summary-cta-box card">
              <div>
                <h4 style={{ color: 'var(--c-white)', marginBottom: 4 }}>Want to explore our complete historical record?</h4>
                <p className="muted body-sm">Discover our founding leadership, chapter charter history, and awards.</p>
              </div>
              <Link to="/milestones" className="btn btn-outline">
                View Full Milestones Page →
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* =========================================================
          5. CONTACT SECRETARIAT SUMMARY SECTION
          ========================================================= */}
      <section className="section home-section contact-summary-section" id="contact-summary">
        <div className="container">
          <div className="section-header text-center reveal-pop">
            <div className="section-badge-pill" style={{ margin: '0 auto 12px' }}>
              <span className="badge-dot" /> Page Summary • Get in Touch
            </div>
            <h2 className="display-2">
              Connect with <span className="text-gold">UAP Quezon</span>
            </h2>
            <p className="body-lg muted" style={{ maxWidth: 620, margin: '14px auto 0' }}>
              Inquiries regarding architectural practice, verified directory listings, membership validation, and community collaboration.
            </p>
            <div className="section-divider" style={{ margin: '16px auto 0' }} />
          </div>

          <div className="home-contact-grid">
            {/* Left: Info Cards */}
            <div className="home-contact-info reveal-left">
              <div className="card home-info-card">
                <h3 className="home-info-title">Chapter Secretariat</h3>
                <p className="muted body-sm" style={{ marginBottom: 20 }}>
                  We welcome questions from clients, building officials, LGU partners, and fellow professionals.
                </p>

                <div className="home-info-item">
                  <div className="home-info-icon"><IconMapPin size={20} /></div>
                  <div>
                    <strong>Office Address</strong>
                    <p className="muted">{contactAddress}</p>
                  </div>
                </div>

                <div className="home-info-item">
                  <div className="home-info-icon"><IconMail size={20} /></div>
                  <div>
                    <strong>Official Email</strong>
                    <a href={`mailto:${contactEmail}`} className="text-gold">{contactEmail}</a>
                  </div>
                </div>

                <div className="home-info-item">
                  <div className="home-info-icon"><IconPhone size={20} /></div>
                  <div>
                    <strong>Phone / Mobile Hotline</strong>
                    <a href={`tel:${contactPhone.replace(/[^0-9+]/g, '')}`} className="muted">{contactPhone}</a>
                  </div>
                </div>

                <div className="home-info-badge card">
                  <div className="flex-center" style={{ gap: 8, justifyContent: 'flex-start' }}>
                    <IconStar size={18} style={{ color: 'var(--c-gold)' }} />
                    <strong style={{ fontSize: '0.85rem', color: '#fff' }}>Republic Act No. 9266 Advisory</strong>
                  </div>
                  <p className="muted" style={{ fontSize: '0.8rem', marginTop: 4 }}>
                    Always ensure that architectural plans and documents are prepared, signed, and sealed exclusively by registered and licensed architects (RLA).
                  </p>
                </div>

                <div style={{ marginTop: 20 }}>
                  <Link to="/contact" className="btn btn-outline" style={{ width: '100%', justifyContent: 'center' }}>
                    Visit Full Contact Page →
                  </Link>
                </div>
              </div>
            </div>

            {/* Right: Quick Inquiry Form */}
            <div className="home-contact-form-wrap reveal-right">
              <div className="card home-form-card">
                <h3 className="home-info-title">Send a Quick Message</h3>
                <p className="muted body-sm" style={{ marginBottom: 20 }}>
                  Drop our secretariat an inquiry and we will get back to you promptly.
                </p>

                {contactSent ? (
                  <div className="contact-success-state text-center" style={{ padding: '36px 16px' }}>
                    <div style={{ color: 'var(--c-green, #22c55e)', marginBottom: 16 }}>
                      <IconMailSent size={48} />
                    </div>
                    <h4 style={{ color: 'var(--c-white)', marginBottom: 8 }}>Inquiry Sent Successfully!</h4>
                    <p className="muted body-sm" style={{ maxWidth: 360, margin: '0 auto 20px' }}>
                      Thank you for reaching out. Your inquiry has been routed to the UAP Quezon Province Chapter secretariat.
                    </p>
                    <button
                      type="button"
                      className="btn btn-outline"
                      onClick={() => setContactSent(false)}
                    >
                      Send Another Inquiry
                    </button>
                  </div>
                ) : (
                  <form onSubmit={handleContactSubmit} className="home-inquiry-form">
                    {contactError && (
                      <div className="form-error-banner">
                        {contactError}
                      </div>
                    )}

                    <div className="form-group">
                      <label className="form-label">Full Name *</label>
                      <input
                        type="text"
                        name="name"
                        value={contactForm.name}
                        onChange={handleContactChange}
                        placeholder="e.g. Ar. Juan Dela Cruz / Engr. Santos"
                        className="input"
                        required
                      />
                    </div>

                    <div className="form-row-2">
                      <div className="form-group">
                        <label className="form-label">Email Address</label>
                        <input
                          type="email"
                          name="email"
                          value={contactForm.email}
                          onChange={handleContactChange}
                          placeholder="name@example.com"
                          className="input"
                        />
                      </div>
                      <div className="form-group">
                        <label className="form-label">Contact Number</label>
                        <input
                          type="text"
                          name="phone"
                          value={contactForm.phone}
                          onChange={handleContactChange}
                          placeholder="0917 123 4567"
                          className="input"
                        />
                      </div>
                    </div>

                    <div className="form-group">
                      <label className="form-label">Subject</label>
                      <input
                        type="text"
                        name="subject"
                        value={contactForm.subject}
                        onChange={handleContactChange}
                        placeholder="Membership inquiry, project referral, LGU concern..."
                        className="input"
                      />
                    </div>

                    <div className="form-group">
                      <label className="form-label">Message *</label>
                      <textarea
                        name="message"
                        value={contactForm.message}
                        onChange={handleContactChange}
                        placeholder="How can the chapter assist you?"
                        rows={4}
                        className="input textarea"
                        required
                      />
                    </div>

                    <button
                      type="submit"
                      disabled={contactSubmitting}
                      className="btn btn-gold"
                      style={{ width: '100%', justifyContent: 'center' }}
                    >
                      {contactSubmitting ? 'Sending Message...' : 'Send Inquiry Message →'}
                    </button>
                  </form>
                )}
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ========== MEMBER PORTAL CTA BANNER ========== */}
      <section className="cta-section">
        <div className="container text-center reveal-pop">
          <p className="eyebrow">Are you a UAP Quezon Member?</p>
          <h2 className="display-2">Access Your <span className="text-gold">Member Portal</span></h2>
          <p className="body-lg muted" style={{ maxWidth: 520, margin: '16px auto 32px' }}>
            Track your chapter dues, upload payment proofs, generate official digital receipts, and manage your verified architect directory profile.
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
