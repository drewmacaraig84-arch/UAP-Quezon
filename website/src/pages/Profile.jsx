import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import { IconArrowLeft, IconArrowRight, IconVerified, IconMapPin, IconQrCode, IconBriefcase, getSocialLinkInfo } from '../components/Icons';
import './Profile.css';

export default function Profile() {
  const { id } = useParams();
  const { data: m, loading, error } = useApi(`/api/member.php?id=${id}`);

  if (loading) return (
    <main className="page-container">
      <div className="container profile-loading">
        <div className="skeleton" style={{ height: 300, borderRadius: 'var(--r-lg)', marginBottom: 24 }} />
        <div className="skeleton" style={{ height: 40, width: '60%', borderRadius: 8, marginBottom: 12 }} />
        <div className="skeleton" style={{ height: 24, width: '40%', borderRadius: 8 }} />
      </div>
    </main>
  );

  if (error || !m) {
    const isHold = error?.toLowerCase().includes('administrative hold') || error?.toLowerCase().includes('pending certification');
    const isPending = error?.toLowerCase().includes('pending approval') || error?.toLowerCase().includes('payment');

    return (
      <main className="page-container">
        <div className="container text-center" style={{ padding: 'var(--space-10) 0', maxWidth: 580, margin: '0 auto' }}>
          <div className="card" style={{ padding: 'var(--space-8) var(--space-6)', borderRadius: 'var(--r-lg)' }}>
            <div style={{
              width: 56,
              height: 56,
              borderRadius: '50%',
              background: isHold ? 'rgba(239, 68, 68, 0.12)' : isPending ? 'rgba(245, 158, 11, 0.12)' : 'rgba(255, 255, 255, 0.05)',
              color: isHold ? '#f87171' : isPending ? '#fbbf24' : 'var(--c-text-3)',
              display: 'inline-flex',
              alignItems: 'center',
              justifyContent: 'center',
              marginBottom: 16
            }}>
              {isHold ? (
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
              ) : isPending ? (
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              ) : (
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              )}
            </div>
            <h2 style={{ fontSize: '1.4rem', fontWeight: 700, marginBottom: 12, color: 'var(--c-text-1)' }}>
              {isHold ? 'Profile Temporarily Unavailable' : isPending ? 'Profile Pending Activation' : 'Profile Not Found'}
            </h2>
            <p className="muted" style={{ fontSize: '0.95rem', lineHeight: 1.6, marginBottom: 24 }}>
              {error || 'The requested architect profile could not be found or has been unpublished.'}
            </p>
            <div style={{ display: 'flex', gap: 12, justifyContent: 'center', flexWrap: 'wrap' }}>
              <Link to="/directory" className="btn btn-outline">
                <IconArrowLeft size={16} /> Back to Directory
              </Link>
              <a href="/login" className="btn btn-primary">
                Member Portal &rarr;
              </a>
            </div>
          </div>
        </div>
      </main>
    );
  }

  const initials = m.name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
  const socialInfo = m.link_url ? getSocialLinkInfo(m.link_url, m.link_type) : null;

  return (
    <main className="page-container">
      <div className="container profile-container">
        {m.is_admin_preview && (
          <div style={{
            background: 'rgba(245, 158, 11, 0.12)',
            border: '1px solid rgba(245, 158, 11, 0.35)',
            borderRadius: 'var(--r-md)',
            padding: '12px 18px',
            marginBottom: 20,
            display: 'flex',
            alignItems: 'center',
            gap: 12,
            color: '#fbbf24',
            fontSize: '0.9rem'
          }}>
            <span style={{ fontSize: '1.25rem' }}>⚠️</span>
            <div>
              <strong>Chapter Admin Preview:</strong> {m.preview_notice || 'This profile is currently in hold/pending status.'}
              <div style={{ fontSize: '0.8rem', opacity: 0.85, marginTop: 2 }}>
                Visible to you because you are authenticated as an Administrator. Public visitors see an administrative hold notice until status is updated.
              </div>
            </div>
          </div>
        )}

        <Link to="/directory" className="profile-back reveal">
          <IconArrowLeft size={16} />
          <span>Back to Directory</span>
        </Link>

        {/* Profile Hero Card */}
        <div className="profile-hero card reveal-pop">
          <div className="profile-photo-wrap">
            {m.photo_url && (
              <img
                src={m.photo_url}
                alt={m.name}
                className="profile-photo"
                onError={(e) => {
                  e.currentTarget.style.display = 'none';
                  const sib = e.currentTarget.parentElement?.querySelector('.profile-initials');
                  if (sib) sib.style.display = 'flex';
                }}
              />
            )}
            <div className="profile-initials" style={{ display: m.photo_url ? 'none' : 'flex' }}>{initials}</div>
          </div>

          <div className="profile-info">
            <div className="profile-badges">
              <span className="badge badge-verified">
                <IconVerified size={13} />
                <span>Good Standing</span>
              </span>
              {m.specialty?.split(',').slice(0, 2).map((s, i) => (
                <span key={i} className="badge badge-gold">{s.trim()}</span>
              ))}
            </div>

            <h1 className="profile-name display-2">{m.name}</h1>

            <div className="profile-company-row">
              <div className="profile-company-text-side">
                {m.company_name ? (
                  <p className="profile-company-highlight">{m.company_name}</p>
                ) : (
                  <p className="profile-company-highlight">{m.role_title || 'Licensed Architect'}</p>
                )}

                <div className="profile-meta">
                  {m.company_name && m.role_title && (
                    <span className="profile-meta-item">
                      <IconBriefcase size={15} style={{ color: 'var(--c-gold)' }} />
                      <span>{m.role_title}</span>
                    </span>
                  )}
                  {m.location && (
                    <span className="profile-meta-item">
                      <IconMapPin size={15} />
                      <span>{m.location}</span>
                    </span>
                  )}
                </div>
              </div>

              {/* Company Logo — positioned in lower-right of hero */}
              <div className="profile-company-logo-wrap">
                {m.company_logo_url ? (
                  <img
                    src={m.company_logo_url}
                    alt={m.company_name || 'Company Logo'}
                    className="profile-company-logo-img"
                    onError={(e) => {
                      e.currentTarget.style.display = 'none';
                      const ph = e.currentTarget.parentElement?.querySelector('.profile-company-logo-placeholder');
                      if (ph) ph.style.display = 'flex';
                    }}
                  />
                ) : null}
                <div
                  className="profile-company-logo-placeholder"
                  style={{ display: m.company_logo_url ? 'none' : 'flex' }}
                >
                  <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--c-gold)', opacity: 0.35 }}>
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <path d="M3 9h18M9 21V9"/>
                  </svg>
                  {m.company_name && (
                    <span className="profile-company-logo-initials">
                      {m.company_name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()}
                    </span>
                  )}
                </div>
              </div>
            </div>

            {/* Social Showcase Links in One Unified Horizontal Row */}
            {(() => {
              const socialLinksList = (m.links && m.links.length > 0)
                ? m.links.map(l => getSocialLinkInfo(l.url, l.type)).filter(Boolean)
                : (socialInfo ? [socialInfo] : []);

              if (socialLinksList.length === 0) return null;

              return (
                <div className="profile-social-links-bar">
                  {socialLinksList.map((s, idx) => (
                    <a
                      key={idx}
                      href={s.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className={`btn btn-sm arch-social-${s.type} profile-social-btn`}
                    >
                      <s.Icon size={14} />
                      <span>Visit {s.label} &rarr;</span>
                    </a>
                  ))}
                </div>
              );
            })()}
          </div>
        </div>

        {/* Top 2-Column Grid: Professional Background & Profile Details (Same Height) */}
        <div className="profile-details-grid reveal-stagger">
          {/* Professional Background */}
          <div className="profile-section card profile-equal-card">
            <h2 className="profile-section-title">Professional Background</h2>
            {m.achievements ? (
              <p className="muted" style={{ lineHeight: 1.75, whiteSpace: 'pre-line' }}>{m.achievements}</p>
            ) : (
              <p className="muted">No background information available.</p>
            )}
          </div>

          {/* Profile Details */}
          <div className="profile-section card profile-equal-card">
            <h2 className="profile-section-title">Profile Details</h2>
            <dl className="profile-dl">
              <dt>Full Name</dt><dd>{m.name}</dd>
              {m.company_name && <><dt>Company / Firm</dt><dd>{m.company_name}</dd></>}
              {m.location && <><dt>Company / Office Address</dt><dd>{m.location}</dd></>}
              {m.role_title && <><dt>Title / Role</dt><dd>{m.role_title}</dd></>}
              {m.specialty && <><dt>Specialization</dt><dd>{m.specialty}</dd></>}
              {(() => {
                const sList = (m.links && m.links.length > 0)
                  ? m.links.map(l => getSocialLinkInfo(l.url, l.type)).filter(Boolean)
                  : (socialInfo ? [socialInfo] : []);

                if (sList.length === 0) return null;

                return (
                  <>
                    <dt>Showcase {sList.length > 1 ? 'Links' : 'Link'}</dt>
                    <dd style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                      {sList.map((s, idx) => (
                        <a
                          key={idx}
                          href={s.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          style={{ color: 'var(--c-gold)', display: 'inline-flex', alignItems: 'center', gap: 5, textDecoration: 'none', fontWeight: 600 }}
                        >
                          <s.Icon size={14} />
                          <span>{s.label} ({s.url.replace(/^https?:\/\//i, '').split('/')[0]}) &rarr;</span>
                        </a>
                      ))}
                    </dd>
                  </>
                );
              })()}
            </dl>
          </div>
        </div>

        {/* Honors, Distinctions & Awards (Landscape Card Underneath Combined Width) */}
        {m.awards && (
          <div className="profile-awards-landscape card reveal-pop">
            <h2 className="profile-section-title" style={{ color: 'var(--c-gold)' }}>Honors, Distinctions &amp; Awards</h2>
            <p className="muted" style={{ lineHeight: 1.75, fontSize: '0.95rem', whiteSpace: 'pre-line', margin: 0 }}>{m.awards}</p>
          </div>
        )}

        {/* COMPLETED WORKS */}
        {m.projects && m.projects.length > 0 && (
          <div className="profile-completed-works-section reveal-pop">
            <div className="completed-works-header">
              <p className="eyebrow" style={{ letterSpacing: '0.14em', textTransform: 'uppercase', marginBottom: 4 }}>Portfolio &amp; Architectural Practice</p>
              <h2 className="heading-1" style={{ letterSpacing: '0.04em', textTransform: 'uppercase', marginBottom: 24 }}>
                COMPLETED <span className="text-gold">WORKS</span>
              </h2>
            </div>
            
            <div className="completed-works-grid reveal-stagger">
              {m.projects.map((proj, pIdx) => {
                const cover = proj.cover_url || proj.photos?.[0];
                const projId = proj.id || `proj_${pIdx + 1}`;
                return (
                  <Link
                    key={projId}
                    to={`/profile/${id}/project/${projId}`}
                    className="completed-work-card card"
                    aria-label={`View details for ${proj.title}`}
                  >
                    <div className="completed-work-photo-wrap">
                      <img
                        src={cover}
                        alt={proj.title}
                        onError={(e) => {
                          e.currentTarget.onerror = null;
                          e.currentTarget.src = '/uap_logo.jpg';
                        }}
                      />
                      {/* Hover Overlay matching Image 3 */}
                      <div className="completed-work-hover-overlay">
                        <div className="completed-work-hover-content">
                          {proj.category && (
                            <span className="completed-work-hover-cat">{proj.category}</span>
                          )}
                          <h3 className="completed-work-hover-title">{proj.title}</h3>
                          {proj.location && (
                            <p className="completed-work-hover-loc">
                              <IconMapPin size={12} style={{ display: 'inline', verticalAlign: 'middle', marginRight: 4 }} />
                              <span>{proj.location}</span>
                            </p>
                          )}
                          <div className="completed-work-hover-action">
                            <span>View Full Project Details</span>
                            <IconArrowRight size={14} />
                          </div>
                        </div>
                      </div>
                    </div>
                  </Link>
                );
              })}
            </div>
          </div>
        )}
      </div>
    </main>
  );
}
