import { useState } from 'react';
import { useApi } from '../hooks/useApi';
import { IconMapPin, IconMail, IconPhone, IconMailSent } from '../components/Icons';
import './Contact.css';

export default function Contact() {
  const { data: settings } = useApi('/api/settings.php');
  const [form, setForm] = useState({ name: '', email: '', phone: '', subject: '', message: '' });
  const [submitting, setSubmitting] = useState(false);
  const [sent, setSent] = useState(false);
  const [error, setError] = useState('');

  const contactEmail = settings?.contact_email || 'quezon@united-architects.ph';
  const contactAddress = settings?.contact_address || 'Lucena City, Quezon Province, Philippines';
  const contactPhone = settings?.contact_phone || '+63 (0) XXXX XXXX';
  const hoursWeekdays = settings?.office_hours_weekdays || '9:00 AM – 5:00 PM';
  const hoursSaturday = settings?.office_hours_saturday || '9:00 AM – 12:00 PM';
  const hoursSunday = settings?.office_hours_sunday || 'Closed';

  const handleChange = (e) => {
    setError('');
    setForm({ ...form, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    if (!form.name.trim()) {
      setError('Please provide your full name.');
      return;
    }

    if (!form.email.trim() && !form.phone.trim()) {
      setError('Please provide at least one contact method (Email address or Mobile/Contact number).');
      return;
    }

    if (form.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
      setError('Please provide a valid email address.');
      return;
    }

    if (!form.message.trim()) {
      setError('Please enter your message.');
      return;
    }

    setSubmitting(true);

    try {
      const res = await fetch('/api/contact.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form)
      });
      const data = await res.json();

      if (res.ok && data.success) {
        setSent(true);
        setForm({ name: '', email: '', phone: '', subject: '', message: '' });
      } else {
        setError(data.error || 'Failed to submit inquiry. Please try again.');
      }
    } catch (err) {
      setError('Unable to reach the server. Please check your connection and try again.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <main className="page-container">
      {/* Hero */}
      <div className="contact-hero">
        <div className="container reveal-pop">
          <p className="eyebrow">Get in Touch</p>
          <h1 className="display-2">Contact <span className="text-gold">Us</span></h1>
          <p className="body-lg muted" style={{ marginTop: 12, maxWidth: 500 }}>
            Reach out to the UAP Quezon Chapter Secretariat for inquiries, membership, and professional matters.
          </p>
        </div>
      </div>

      <div className="container contact-body">
        <div className="contact-grid">
          {/* Info */}
          <div className="contact-info reveal-left">
            <div className="contact-card card">
              <h2 className="contact-section-title">Chapter Secretariat</h2>

              {[
                {
                  icon: <IconMapPin size={20} />,
                  label: 'Address',
                  value: contactAddress,
                },
                {
                  icon: <IconMail size={20} />,
                  label: 'Email',
                  value: contactEmail,
                  href: `mailto:${contactEmail}`,
                },
                {
                  icon: <IconPhone size={20} />,
                  label: 'Phone',
                  value: contactPhone,
                },
              ].map(({ icon, label, value, href }) => (
                <div key={label} className="contact-info-item">
                  <span className="contact-info-icon">{icon}</span>
                  <div>
                    <p className="contact-info-label">{label}</p>
                    {href ? (
                      <a href={href} className="contact-info-value">{value}</a>
                    ) : (
                      <p className="contact-info-value">{value}</p>
                    )}
                  </div>
                </div>
              ))}
            </div>

            <div className="contact-card card contact-hours">
              <h3 className="contact-section-title" style={{ fontSize: '1rem' }}>Office Hours</h3>
              <div className="hours-grid">
                <span className="muted body-sm">Monday – Friday</span><span className="body-sm">{hoursWeekdays}</span>
                <span className="muted body-sm">Saturday</span><span className="body-sm">{hoursSaturday}</span>
                <span className="muted body-sm">Sunday &amp; Holidays</span><span className="body-sm text-gold">{hoursSunday}</span>
              </div>
            </div>
          </div>

          {/* Form */}
          <div className="contact-form-wrap card reveal-right">
            <h2 className="contact-section-title">Send a Message</h2>
            {sent ? (
              <div className="contact-success">
                <div className="contact-success-icon" style={{ color: 'var(--c-gold)' }}>
                  <IconMailSent size={48} />
                </div>
                <h3>Inquiry Received!</h3>
                <p className="muted">Thank you for reaching out. Your message has been directly sent to the UAP Quezon Chapter Secretariat. We will get in touch with you shortly.</p>
                <button className="btn btn-outline" onClick={() => setSent(false)} style={{ marginTop: '16px' }}>Send Another Inquiry</button>
              </div>
            ) : (
              <form onSubmit={handleSubmit} className="contact-form">
                {error && (
                  <div style={{ padding: '12px 14px', borderRadius: '8px', background: 'rgba(239, 68, 68, 0.12)', border: '1px solid rgba(239, 68, 68, 0.3)', color: '#fca5a5', fontSize: '0.875rem', marginBottom: '16px' }}>
                    {error}
                  </div>
                )}
                <div className="form-group">
                  <label htmlFor="name">Full Name <span style={{ color: 'var(--c-gold)' }}>*</span></label>
                  <input id="name" name="name" className="input" placeholder="Juan dela Cruz" required value={form.name} onChange={handleChange} disabled={submitting} />
                </div>

                <div className="form-row">
                  <div className="form-group">
                    <label htmlFor="email">
                      Email Address <span className="muted" style={{ fontSize: '0.78rem', fontWeight: 400 }}>(or phone)</span>
                    </label>
                    <input id="email" name="email" type="email" className="input" placeholder="juan@email.com" value={form.email} onChange={handleChange} disabled={submitting} />
                  </div>
                  <div className="form-group">
                    <label htmlFor="phone">
                      Contact Number <span className="muted" style={{ fontSize: '0.78rem', fontWeight: 400 }}>(or email)</span>
                    </label>
                    <input id="phone" name="phone" type="tel" className="input" placeholder="0912 345 6789 / +63..." value={form.phone} onChange={handleChange} disabled={submitting} />
                  </div>
                </div>

                <div className="form-group">
                  <label htmlFor="subject">Subject</label>
                  <input id="subject" name="subject" className="input" placeholder="Membership Inquiry / Project Consultation / etc." value={form.subject} onChange={handleChange} disabled={submitting} />
                </div>
                <div className="form-group">
                  <label htmlFor="message">Message <span style={{ color: 'var(--c-gold)' }}>*</span></label>
                  <textarea id="message" name="message" className="input" rows="5" placeholder="How can the UAP Quezon Chapter assist you?" required value={form.message} onChange={handleChange} disabled={submitting} />
                </div>
                <button type="submit" className="btn btn-gold" style={{ alignSelf: 'flex-start' }} disabled={submitting}>
                  {submitting ? 'Sending Message...' : 'Send Message'}
                </button>
              </form>
            )}
          </div>
        </div>
      </div>
    </main>
  );
}

