import { useState, useEffect } from 'react';
import { Link, NavLink, useLocation } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import './Navbar.css';

const NAV_LINKS = [
  { to: '/',         label: 'Home' },
  { to: '/directory',label: 'Directory' },
  { to: '/about',    label: 'About' },
  { to: '/news',     label: 'News' },
  { to: '/partners', label: 'Partners' },
  { to: '/contact',  label: 'Contact' },
];

export default function Navbar() {
  const { data: settings } = useApi('/api/settings.php');
  const orgName = settings?.org_name || 'UAP Quezon';
  const [scrolled, setScrolled] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const [badgeErrors, setBadgeErrors] = useState({});
  const [logoError, setLogoError] = useState(false);
  const location = useLocation();

  // Close menu on route change
  useEffect(() => { setMenuOpen(false); }, [location]);

  // Add/remove scrolled class
  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 40);
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  const logoSrc = logoError ? '/logo.jpg' : (settings?.logo || '/public/logo.jpg');

  const activeBadges = [1, 2, 3]
    .map((num) => ({
      num,
      src: settings?.[`header_image_${num}`],
    }))
    .filter((b) => Boolean(b.src) && !badgeErrors[b.num]);

  return (
    <header className={`navbar${scrolled ? ' scrolled' : ''}`}>
      <div className="navbar-inner container-wide">
        {/* Brand & Badges */}
        <div className="navbar-brand-group">
          <Link to="/" className="navbar-brand">
            <img 
              key={logoSrc}
              src={logoSrc} 
              alt={orgName} 
              className="navbar-logo" 
              loading="eager"
              decoding="async"
              onError={() => {
                if (!logoError) setLogoError(true);
              }} 
            />
            <div className="navbar-brand-text">
              <span className="navbar-brand-title">{orgName.split(' ').slice(0, 2).join(' ')}</span>
              <span className="navbar-brand-sub">Chapter</span>
            </div>
          </Link>

          {/* Header Badges / Partner Logos */}
          {activeBadges.length > 0 && (
            <div className="navbar-badges">
              {activeBadges.map(({ num, src }) => (
                <div key={num} className="navbar-badge-box has-image">
                  <img 
                    key={src}
                    src={src} 
                    alt={`Affiliation badge ${num}`} 
                    className="navbar-badge-img" 
                    loading="eager"
                    decoding="async"
                    onError={() => {
                      setBadgeErrors((prev) => ({ ...prev, [num]: true }));
                    }}
                  />
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Desktop Nav */}
        <nav className="navbar-links">
          {NAV_LINKS.map(({ to, label }) => (
            <NavLink
              key={to}
              to={to}
              end={to === '/'}
              className={({ isActive }) => `navbar-link${isActive ? ' active' : ''}`}
            >
              {label}
            </NavLink>
          ))}
        </nav>

        {/* CTA + Hamburger */}
        <div className="navbar-actions">
          <a
            href="/auth/login.php"
            className="btn btn-gold navbar-cta"
            target="_self"
          >
            Member Portal
          </a>
          <button
            className={`navbar-hamburger${menuOpen ? ' open' : ''}`}
            onClick={() => setMenuOpen(!menuOpen)}
            aria-label="Toggle menu"
          >
            <span /><span /><span />
          </button>
        </div>
      </div>

      {/* Mobile Drawer */}
      <div className={`navbar-drawer${menuOpen ? ' open' : ''}`}>
        {NAV_LINKS.map(({ to, label }) => (
          <NavLink
            key={to}
            to={to}
            end={to === '/'}
            className={({ isActive }) => `drawer-link${isActive ? ' active' : ''}`}
          >
            {label}
          </NavLink>
        ))}
        <a
          href="/auth/login.php"
          className="btn btn-gold drawer-cta"
        >
          Member Portal
        </a>
      </div>
    </header>
  );
}
