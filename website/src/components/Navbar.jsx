import { useState, useEffect } from 'react';
import { Link, NavLink, useLocation } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import './Navbar.css';

const NAV_LINKS = [
  { to: '/',           label: 'Home' },
  { to: '/about',      label: 'About' },
  { to: '/architects', label: 'Architects' },
  { to: '/news',       label: 'News & Events' },
  { to: '/milestones', label: 'Milestones' },
  { to: '/contact',    label: 'Contact' },
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
  const [prevPath, setPrevPath] = useState(location.pathname);
  if (prevPath !== location.pathname) {
    setPrevPath(location.pathname);
    setMenuOpen(false);
  }

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

        {/* Actions */}
        <div className="navbar-actions">
          <a
            href="/auth/login.php"
            className="btn btn-portal"
          >
            Portal Login
          </a>

          {/* Mobile hamburger */}
          <button
            type="button"
            className={`navbar-burger${menuOpen ? ' open' : ''}`}
            onClick={() => setMenuOpen(!menuOpen)}
            aria-label="Toggle navigation menu"
            aria-expanded={menuOpen}
          >
            <span />
            <span />
            <span />
          </button>
        </div>
      </div>

      {/* Mobile Drawer */}
      <div className={`navbar-drawer${menuOpen ? ' open' : ''}`}>
        <nav className="navbar-drawer-links">
          {NAV_LINKS.map(({ to, label }) => (
            <NavLink
              key={to}
              to={to}
              end={to === '/'}
              className={({ isActive }) => `navbar-drawer-link${isActive ? ' active' : ''}`}
              onClick={() => setMenuOpen(false)}
            >
              {label}
            </NavLink>
          ))}
          <a
            href="/auth/login.php"
            className="btn btn-portal"
            style={{ marginTop: '1rem', width: '100%', justifyContent: 'center' }}
          >
            Portal Login
          </a>
        </nav>
      </div>
    </header>
  );
}
