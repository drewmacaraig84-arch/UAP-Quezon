/**
 * UAP Quezon Website — SVG Icon Library
 * All icons use clean stroke-based vector paths.
 * Default: 24x24, strokeWidth=1.75, currentColor
 */

const defaults = {
  width: 24,
  height: 24,
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 1.75,
  strokeLinecap: 'round',
  strokeLinejoin: 'round',
};

const Svg = ({ size = 24, className = '', children, ...rest }) => (
  <svg
    width={size}
    height={size}
    viewBox={defaults.viewBox}
    fill={defaults.fill}
    stroke={defaults.stroke}
    strokeWidth={defaults.strokeWidth}
    strokeLinecap={defaults.strokeLinecap}
    strokeLinejoin={defaults.strokeLinejoin}
    className={className}
    {...rest}
  >
    {children}
  </svg>
);

/* Architecture / Classic Column Building */
export const IconBuilding = (p) => (
  <Svg {...p}>
    <path d="M3 21h18" />
    <path d="M5 21V7l7-4 7 4v14" />
    <path d="M9 21v-4a3 3 0 0 1 6 0v4" />
    <path d="M9 9h1" />
    <path d="M14 9h1" />
    <path d="M9 13h1" />
    <path d="M14 13h1" />
  </Svg>
);

/* Sustainable Design / Eco Leaf */
export const IconLeaf = (p) => (
  <Svg {...p}>
    <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10z" />
    <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12" />
  </Svg>
);

/* Education / Architecture Compass */
export const IconDraftingCompass = (p) => (
  <Svg {...p}>
    <circle cx="12" cy="5" r="2" />
    <path d="m10.56 7.44-5.62 9.73" />
    <path d="m13.44 7.44 5.62 9.73" />
    <path d="M5 21h14" />
    <path d="m9 21 3-5 3 5" />
  </Svg>
);

/* Community / Collaboration / Handshake */
export const IconHandshake = (p) => (
  <Svg {...p}>
    <path d="m11 17 2 2a1 1 0 1 0 3-3" />
    <path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4" />
    <path d="m21 3 1 11h-1" />
    <path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3" />
    <path d="M3 4h8" />
  </Svg>
);

/* News / Newspaper */
export const IconNewspaper = (p) => (
  <Svg {...p}>
    <path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 0-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2" />
    <path d="M18 14h-8" />
    <path d="M15 18h-5" />
    <path d="M10 6h8v4h-8V6Z" />
  </Svg>
);

/* Sent Mail / Delivery Confirmation */
export const IconMailSent = (p) => (
  <Svg {...p}>
    <path d="M22 10.5V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v12c0 1.1.9 2 2 2h8" />
    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
    <path d="M16 19h6" />
    <path d="m19 16 3 3-3 3" />
  </Svg>
);

/* Search */
export const IconSearch = (p) => (
  <Svg {...p}>
    <circle cx="11" cy="11" r="8" />
    <path d="m21 21-4.35-4.35" />
  </Svg>
);

/* Location Map Pin */
export const IconMapPin = (p) => (
  <Svg {...p}>
    <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z" />
    <circle cx="12" cy="10" r="3" />
  </Svg>
);

/* Calendar */
export const IconCalendar = (p) => (
  <Svg {...p}>
    <rect width="18" height="18" x="3" y="4" rx="2" ry="2" />
    <line x1="16" x2="16" y1="2" y2="6" />
    <line x1="8" x2="8" y1="2" y2="6" />
    <line x1="3" x2="21" y1="10" y2="10" />
  </Svg>
);

/* Sponsors Star */
export const IconStar = (p) => (
  <Svg {...p}>
    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
  </Svg>
);

/* Verified Badge Check Circle */
export const IconVerified = (p) => (
  <Svg {...p}>
    <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" />
    <path d="m9 12 2 2 4-4" />
  </Svg>
);

/* Simple Checkmark */
export const IconCheck = (p) => (
  <Svg {...p}>
    <path d="M20 6 9 17l-5-5" />
  </Svg>
);

/* Email */
export const IconMail = (p) => (
  <Svg {...p}>
    <rect width="20" height="16" x="2" y="4" rx="2" />
    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
  </Svg>
);

/* Phone */
export const IconPhone = (p) => (
  <Svg {...p}>
    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.62 3.38 2 2 0 0 1 3.61 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.57a16 16 0 0 0 6 6l.96-.96a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.73 16z" />
  </Svg>
);

/* Eye / Preview */
export const IconEye = (p) => (
  <Svg {...p}>
    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
    <circle cx="12" cy="12" r="3" />
  </Svg>
);

/* Arrow Left */
export const IconArrowLeft = (p) => (
  <Svg {...p}>
    <path d="m12 19-7-7 7-7" />
    <path d="M19 12H5" />
  </Svg>
);

/* Arrow Right */
export const IconArrowRight = (p) => (
  <Svg {...p}>
    <path d="M5 12h14" />
    <path d="m12 5 7 7-7 7" />
  </Svg>
);

/* Chevron Left */
export const IconChevronLeft = (p) => (
  <Svg {...p}>
    <path d="m15 18-6-6 6-6" />
  </Svg>
);

/* Chevron Right */
export const IconChevronRight = (p) => (
  <Svg {...p}>
    <path d="m9 18 6-6-6-6" />
  </Svg>
);

/* Chevron Down */
export const IconChevronDown = (p) => (
  <Svg {...p}>
    <path d="m6 9 6 6 6-6" />
  </Svg>
);

/* Chevron Up */
export const IconChevronUp = (p) => (
  <Svg {...p}>
    <path d="m18 15-6-6-6 6" />
  </Svg>
);

/* Close X */
export const IconX = (p) => (
  <Svg {...p}>
    <path d="M18 6 6 18" />
    <path d="m6 6 12 12" />
  </Svg>
);

/* QR Code */
export const IconQrCode = (p) => (
  <Svg {...p}>
    <rect width="5" height="5" x="3" y="3" rx="1" />
    <rect width="5" height="5" x="16" y="3" rx="1" />
    <rect width="5" height="5" x="3" y="16" rx="1" />
    <path d="M21 16h-3a2 2 0 0 0-2 2v3" />
    <path d="M21 21v.01" />
    <path d="M12 7v3a2 2 0 0 1-2 2H7" />
    <path d="M3 12h.01" />
    <path d="M12 3h.01" />
    <path d="M12 16v.01" />
    <path d="M16 12h1" />
    <path d="M21 12v.01" />
    <path d="M12 21v-1" />
  </Svg>
);

/* External Link */
export const IconExternalLink = (p) => (
  <Svg {...p}>
    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
    <polyline points="15 3 21 3 21 9" />
    <line x1="10" y1="14" x2="21" y2="3" />
  </Svg>
);

/* Briefcase / Company / Firm */
export const IconBriefcase = (p) => (
  <Svg {...p}>
    <rect width="20" height="14" x="2" y="7" rx="2" ry="2" />
    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
  </Svg>
);

/* Facebook */
export const IconFacebook = (p) => (
  <Svg {...p}>
    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />
  </Svg>
);

/* Instagram */
export const IconInstagram = (p) => (
  <Svg {...p}>
    <rect width="20" height="20" x="2" y="2" rx="5" ry="5" />
    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
    <line x1="17.5" x2="17.51" y1="6.5" y2="6.5" />
  </Svg>
);

/* LinkedIn */
export const IconLinkedin = (p) => (
  <Svg {...p}>
    <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z" />
    <rect width="4" height="12" x="2" y="9" />
    <circle cx="4" cy="4" r="2" />
  </Svg>
);

/* YouTube */
export const IconYoutube = (p) => (
  <Svg {...p}>
    <path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z" />
    <polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02" />
  </Svg>
);

/* Telegram */
export const IconTelegram = (p) => (
  <Svg {...p}>
    <line x1="22" y1="2" x2="11" y2="13" />
    <polygon points="22 2 15 22 11 13 2 9 22 2" />
  </Svg>
);

/* Globe / Website */
export const IconGlobe = (p) => (
  <Svg {...p}>
    <circle cx="12" cy="12" r="10" />
    <line x1="2" y1="12" x2="22" y2="12" />
    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
  </Svg>
);

/* Empty state / Search fallback */
export const IconEmptyDirectory = (p) => (
  <Svg size={48} {...p}>
    <path d="M4 21h16" />
    <path d="M4 21V7l8-4 8 4v14" />
    <path d="M9 21v-4a3 3 0 0 1 6 0v4" />
    <path d="M9 9h2" />
    <path d="M13 9h2" />
    <path d="M9 13h2" />
    <path d="M13 13h2" />
  </Svg>
);

/* Sparkles / Platinum Tier */
export const IconSparkles = (p) => (
  <Svg {...p}>
    <path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3L12 3Z" />
    <path d="M19 3v4" />
    <path d="M21 5h-4" />
  </Svg>
);

/* Grid / Products */
export const IconGrid = (p) => (
  <Svg {...p}>
    <rect width="7" height="7" x="3" y="3" rx="1" />
    <rect width="7" height="7" x="14" y="3" rx="1" />
    <rect width="7" height="7" x="14" y="14" rx="1" />
    <rect width="7" height="7" x="3" y="14" rx="1" />
  </Svg>
);

/**
 * Smart helper to detect social link type, label, and appropriate SVG Icon
 */
export function getSocialLinkInfo(linkUrl, explicitType) {
  if (!linkUrl) return null;
  let type = (explicitType || 'auto').toLowerCase();
  const url = linkUrl.trim();

  if (type === 'auto') {
    const u = url.toLowerCase();
    if (u.includes('facebook.com') || u.includes('fb.com') || u.includes('fb.me')) type = 'facebook';
    else if (u.includes('instagram.com') || u.includes('instagr.am')) type = 'instagram';
    else if (u.includes('linkedin.com')) type = 'linkedin';
    else if (u.includes('youtube.com') || u.includes('youtu.be')) type = 'youtube';
    else if (u.includes('t.me') || u.includes('telegram.me') || u.includes('telegram.org')) type = 'telegram';
    else type = 'website';
  }

  const map = {
    facebook: { type: 'facebook', label: 'Facebook', Icon: IconFacebook, color: '#1877f2' },
    instagram: { type: 'instagram', label: 'Instagram', Icon: IconInstagram, color: '#e1306c' },
    linkedin: { type: 'linkedin', label: 'LinkedIn', Icon: IconLinkedin, color: '#0a66c2' },
    youtube: { type: 'youtube', label: 'YouTube', Icon: IconYoutube, color: '#ff0000' },
    telegram: { type: 'telegram', label: 'Telegram', Icon: IconTelegram, color: '#229ed9' },
    website: { type: 'website', label: 'Website', Icon: IconGlobe, color: 'var(--c-gold)' },
  };

  const info = map[type] || map.website;
  return {
    ...info,
    url: /^https?:\/\//i.test(url) ? url : `https://${url.replace(/^\/+/, '')}`,
  };
}
