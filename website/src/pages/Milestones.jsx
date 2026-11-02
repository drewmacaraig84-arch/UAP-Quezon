import { useApi } from '../hooks/useApi';
import { Link } from 'react-router-dom';
import {
  IconBuilding,
  IconLeaf,
  IconHandshake,
  IconStar,
  IconVerified
} from '../components/Icons';
import './Milestones.css';

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

export default function Milestones() {
  const { data: milestonesData, loading } = useApi('/api/milestones.php');
  const milestones = (Array.isArray(milestonesData) && milestonesData.length > 0)
    ? milestonesData
    : DEFAULT_MILESTONES;

  return (
    <main className="page-container milestones-page">
      {/* Page Hero */}
      <section className="milestones-hero">
        <div className="container reveal-pop">
          <p className="eyebrow">Built Through Time • 1977 to Present</p>
          <h1 className="display-1">
            Chapter <span className="text-gold">Milestones</span> &amp; Heritage
          </h1>
          <p className="body-lg muted" style={{ maxWidth: 680, margin: '16px auto 0' }}>
            Nearly five decades of architectural leadership, professional excellence, and nation-building across Quezon Province as we approach our Golden 50th Anniversary.
          </p>
        </div>
      </section>

      {/* Leadership & Legacy Overview */}
      <section className="section" style={{ paddingBottom: 0 }}>
        <div className="container">
          <div className="milestones-legacy-grid reveal-stagger">
            <div className="legacy-card card">
              <span className="legacy-icon"><IconBuilding size={26} /></span>
              <h3 className="text-gold">First in District B4</h3>
              <p className="muted body-sm">
                Chartered on November 11, 1977, UAP-QPC was the very first chapter established in District B4 and the 18th recognized nationwide.
              </p>
            </div>
            <div className="legacy-card card">
              <span className="legacy-icon"><IconStar size={26} /></span>
              <h3 className="text-gold">27 Chapter Presidents</h3>
              <p className="muted body-sm">
                From Founding Chapter President Benecio N. Tolentino, FUAP, through 27 visionary administrations guiding our proud legacy of leadership.
              </p>
            </div>
            <div className="legacy-card card">
              <span className="legacy-icon"><IconVerified size={26} /></span>
              <h3 className="text-gold">300+ Members Strong</h3>
              <p className="muted body-sm">
                A thriving fraternity of licensed architects strengthening competencies through CPD seminars, ethical practice, and community service.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* Detailed Timeline */}
      <section className="section">
        <div className="container">
          <div className="section-header text-center reveal-pop">
            <p className="eyebrow">Our Historical Record</p>
            <h2 className="display-2">
              Chronology of <span className="text-gold">Achievements</span>
            </h2>
            <div className="section-divider" style={{ margin: '16px auto 0' }} />
          </div>

          <div className="full-timeline">
            {loading ? (
              <div className="skeleton-timeline">
                {[1, 2, 3, 4].map(i => (
                  <div key={i} className="skeleton" style={{ height: 140, borderRadius: 12, marginBottom: 20 }} />
                ))}
              </div>
            ) : (
              milestones.map((item, idx) => (
                <div key={item.id ?? idx} className={`timeline-entry ${idx % 2 === 0 ? 'entry-left reveal-left' : 'entry-right reveal-right'}`}>
                  <div className="timeline-marker">
                    <span className="marker-dot" />
                  </div>
                  <div className="timeline-card card">
                    <div className="timeline-card-header">
                      <span className="timeline-badge-year">{item.year}</span>
                      <span className="timeline-seq">Chapter Era #{idx + 1}</span>
                    </div>
                    <h3 className="timeline-item-title">{item.title}</h3>
                    <p className="timeline-item-desc muted">{item.description || item.content}</p>
                  </div>
                </div>
              ))
            )}
            <div className="timeline-central-axis" />
          </div>
        </div>
      </section>

      {/* Signatures & Initiatives Highlight */}
      <section className="section milestones-initiatives-section">
        <div className="container">
          <div className="initiatives-card card reveal-pop">
            <div className="initiatives-grid">
              <div>
                <span className="eyebrow text-gold">Arkitektong Quezonian Signature</span>
                <h2 className="heading-1" style={{ marginTop: 8, color: '#fff' }}>
                  Guhit Arkitektong Quezonian
                </h2>
                <p className="body-md muted" style={{ marginTop: 14 }}>
                  A hallmark annual event serving as an open platform for Quezonian architects to exhibit creative portfolios, present research, and engage in meaningful dialogue with our local communities through the art and practice of architecture.
                </p>
                <div className="initiatives-pills">
                  <span className="init-pill"><IconLeaf size={16} /> Eco-Resilient Design</span>
                  <span className="init-pill"><IconHandshake size={16} /> RA 9266 LGU Clinics</span>
                  <span className="init-pill"><IconBuilding size={16} /> Heritage Documentation</span>
                </div>
              </div>

              <div className="initiatives-cta-box">
                <h4 style={{ color: '#fff', marginBottom: 8 }}>Explore Chapter Directory</h4>
                <p className="body-sm muted" style={{ marginBottom: 20 }}>
                  Meet the licensed architects carrying forward this 50-year legacy of excellence.
                </p>
                <div className="flex-center" style={{ gap: 12, flexWrap: 'wrap' }}>
                  <Link to="/architects" className="btn btn-gold">
                    View Architects Directory →
                  </Link>
                  <Link to="/contact" className="btn btn-outline">
                    Contact Secretariat
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}

