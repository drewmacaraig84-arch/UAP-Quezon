import { useApi } from '../hooks/useApi';
import { IconBuilding, IconLeaf, IconDraftingCompass, IconHandshake } from '../components/Icons';
import './About.css';

const DEFAULT_MILESTONES = [
  { year: '2016', title: 'Chapter Founded', content: 'UAP Quezon Chapter established as IAPOA Chapter 121, bringing together registered architects across the Quezon provinces.' },
  { year: '2018', title: 'Growing Membership', content: 'Membership expanded significantly with architects from Lucena City, Tayabas, Sariaya, and the four districts of Quezon Province joining the chapter.' },
  { year: '2020', title: 'Digital Transformation', content: 'Adopted digital systems for member management, dues processing, and chapter communications.' },
  { year: '2023', title: 'New Leadership', content: 'A new Board of Directors was elected, bringing fresh perspectives and initiatives for chapter growth.' },
  { year: '2024', title: 'Online Architect Directory', content: 'Launched the public Architect Directory to connect clients with verified UAP Quezon architects.' },
];

const MISSION = [
  {
    icon: <IconBuilding size={28} />,
    title: 'Professional Excellence',
    desc: 'Upholding the highest standards of architectural practice, ethics, and professional conduct as mandated by PRC and UAP national standards.',
  },
  {
    icon: <IconLeaf size={28} />,
    title: 'Sustainable Architecture',
    desc: 'Advocating for environmentally responsible and resilient design that addresses the unique coastal and agricultural context of Quezon Island.',
  },
  {
    icon: <IconDraftingCompass size={28} />,
    title: 'Design Education',
    desc: 'Continuous professional development through seminars, workshops, and knowledge-sharing initiatives among chapter members.',
  },
  {
    icon: <IconHandshake size={28} />,
    title: 'Community Engagement',
    desc: "Serving local government units, communities, and organizations across Quezon Province through pro-bono design assistance.",
  },
];

export default function About() {
  const { data: settings } = useApi('/api/settings.php');
  const { data: milestonesData } = useApi('/api/milestones.php');
  const aboutText = settings?.about_us || 'The United Architects of the Philippines — Quezon Chapter (IAPOA Chapter 121) is the official professional organization of licensed architects serving Quezon Province.';
  const rawTimeline = (milestonesData && milestonesData.length > 0) ? milestonesData : DEFAULT_MILESTONES;
  const timeline = rawTimeline.filter((item, index, self) =>
    index === self.findIndex((t) => (t.id ? t.id === item.id : (t.year === item.year && t.title === item.title)))
  );

  return (
    <main className="page-container">
      {/* Hero */}
      <section className="about-page-hero">
        <div className="container reveal-pop">
          <p className="eyebrow">Who We Are</p>
          <h1 className="display-1">About <span className="text-gold">UAP Quezon</span></h1>
          <p className="body-lg muted about-hero-sub">
            {aboutText}
          </p>
        </div>
      </section>

      {/* Mission Grid */}
      <section className="section">
        <div className="container">
          <div className="section-header text-center reveal-pop">
            <p className="eyebrow">Our Purpose</p>
            <h2 className="display-2">Mission &amp; <span className="text-gold">Values</span></h2>
            <div className="section-divider" style={{ margin: '16px auto 0' }} />
          </div>

          <div className="mission-grid reveal-stagger">
            {MISSION.map((m) => (
              <div key={m.title} className="mission-card card">
                <span className="mission-icon">{m.icon}</span>
                <h3 className="mission-title">{m.title}</h3>
                <p className="muted">{m.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Timeline */}
      <section className="section timeline-section">
        <div className="container">
          <div className="section-header reveal-left">
            <p className="eyebrow">Our History</p>
            <h2 className="display-2">Chapter <span className="text-gold">Milestones</span></h2>
            <div className="section-divider" style={{ marginTop: 16 }} />
          </div>

          <div className="timeline">
            {timeline.map((item, i) => (
              <div key={item.id ?? i} className={`timeline-item${i % 2 === 0 ? ' left reveal-left' : ' right reveal-right'}`}>
                <div className="timeline-dot" />
                <div className="timeline-content card">
                  <span className="timeline-year eyebrow">{item.year}</span>
                  <h3 className="timeline-title">{item.title}</h3>
                  <p className="muted">{item.content ?? item.desc}</p>
                </div>
              </div>
            ))}
            <div className="timeline-line" />
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="section about-cta-section">
        <div className="container text-center reveal-pop">
          <p className="eyebrow">Be Part of the Chapter</p>
          <h2 className="display-2">Join <span className="text-gold">UAP Quezon</span></h2>
          <p className="body-lg muted" style={{ maxWidth: 480, margin: '16px auto 32px' }}>
            Are you a licensed architect in Quezon? Register as a chapter member and join our growing community of professionals.
          </p>
          <div className="flex-center" style={{ gap: 16 }}>
            <a href="/auth/login.php" className="btn btn-gold">Member Portal</a>
            <a href="/auth/register.php" className="btn btn-outline">Register Now</a>
          </div>
        </div>
      </section>
    </main>
  );
}
