import { Link } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import './Footer.css';

const PORTAL_URL = '/auth/login.php';
const REGISTER_URL = '/auth/register.php';

export default function Footer() {
  const year = new Date().getFullYear();
  const { data: settings } = useApi('/api/settings.php');
  const logoSrc = settings?.logo || '/public/logo.jpg';
  return (
    <footer className="footer">
      <div className="footer-top container-wide">
        {/* Brand */}
        <div className="footer-brand">
          <div className="footer-logo-wrap">
            <img 
              src={logoSrc} 
              alt="UAP Quezon" 
              onError={(e) => {
                if (!e.currentTarget.src.endsWith('/logo.jpg')) {
                  e.currentTarget.src = '/logo.jpg';
                }
              }}
            />
          </div>
          <p className="footer-brand-name">United Architects<br/>of the Philippines</p>
          <p className="footer-brand-sub">Quezon Chapter</p>
          <p className="footer-desc">
            Promoting architectural excellence, professional integrity, and sustainable development across Oriental and Occidental Quezon since 2016.
          </p>
        </div>

        {/* Quick Links */}
        <div className="footer-col">
          <h4 className="footer-col-title">Navigation</h4>
          <ul>
            {[
              { to: '/', label: 'Home' },
              { to: '/directory', label: 'Architect Directory' },
              { to: '/about', label: 'About the Chapter' },
              { to: '/news', label: 'News & Updates' },
              { to: '/partners', label: 'Featured Partners' },
              { to: '/contact', label: 'Contact' },
            ].map(({ to, label }) => (
              <li key={to}><Link to={to} className="footer-link">{label}</Link></li>
            ))}
          </ul>
        </div>

        {/* Portal Links */}
        <div className="footer-col">
          <h4 className="footer-col-title">Member Portal</h4>
          <ul>
            <li><a href={PORTAL_URL} className="footer-link">Portal Login</a></li>
            <li><a href={REGISTER_URL} className="footer-link">Register</a></li>
          </ul>
        </div>
      </div>

      <div className="footer-bottom container-wide">
        <p>&copy; {year} <strong>United Architects of the Philippines</strong> — Quezon Chapter</p>
        <p className="footer-credit">
          Designed &amp; Developed by <strong>Aries King Nieto</strong> and{' '}
          <a
            href="https://drewportfoliio.vercel.app/"
            target="_blank"
            rel="noopener noreferrer"
            className="footer-credit-link"
          >
            <strong>Drew Macaraig</strong>
          </a>
        </p>
      </div>
    </footer>
  );
}
