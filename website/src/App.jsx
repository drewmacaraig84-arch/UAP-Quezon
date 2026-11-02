import { lazy, Suspense } from 'react';
import { Routes, Route } from 'react-router-dom';
import Navbar from './components/Navbar';
import Footer from './components/Footer';
import SponsorDock from './components/SponsorDock';
import { useScrollReveal } from './hooks/useScrollReveal';

// Lazy-loaded pages
const Home          = lazy(() => import('./pages/Home'));
const Directory     = lazy(() => import('./pages/Directory'));
const Profile       = lazy(() => import('./pages/Profile'));
const ProjectDetail = lazy(() => import('./pages/ProjectDetail'));
const About         = lazy(() => import('./pages/About'));
const News          = lazy(() => import('./pages/News'));
const Milestones    = lazy(() => import('./pages/Milestones'));
const Sponsors      = lazy(() => import('./pages/Sponsors'));
const SponsorDetail = lazy(() => import('./pages/SponsorDetail'));
const Contact       = lazy(() => import('./pages/Contact'));

function PageLoader() {
  return (
    <div style={{ minHeight: '60vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
      <div style={{ textAlign: 'center', color: 'var(--c-text-2)' }}>
        <div style={{ width: 40, height: 40, border: '2px solid var(--c-border)', borderTop: '2px solid var(--c-gold)', borderRadius: '50%', animation: 'spin 0.8s linear infinite', margin: '0 auto 12px' }} />
        <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
        <p style={{ fontSize: '0.85rem' }}>Loading...</p>
      </div>
    </div>
  );
}

export default function App() {
  useScrollReveal();

  return (
    <>
      <Navbar />
      <SponsorDock />
      <Suspense fallback={<PageLoader />}>
        <Routes>
          <Route path="/"           element={<Home />} />
          <Route path="/about"      element={<About />} />
          <Route path="/architects" element={<Directory />} />
          <Route path="/directory"  element={<Directory />} />
          <Route path="/profile/:id" element={<Profile />} />
          <Route path="/profile/:id/project/:projectId" element={<ProjectDetail />} />
          <Route path="/news"       element={<News />} />
          <Route path="/milestones" element={<Milestones />} />
          <Route path="/partners"   element={<Sponsors />} />
          <Route path="/partners/:id" element={<SponsorDetail />} />
          <Route path="/sponsors"   element={<Sponsors />} />
          <Route path="/sponsors/:id" element={<SponsorDetail />} />
          <Route path="/contact"    element={<Contact />} />
        </Routes>
      </Suspense>
      <Footer />
    </>
  );
}
