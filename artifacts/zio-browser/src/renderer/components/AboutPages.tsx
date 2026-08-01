/**
 * Internal "About" pages — renderer-drawn (like the New Tab page).
 *  - about:sayzio  → About Sayzio (the platform)
 *  - about:zio     → About Zio Browser (this app)
 * Animations come from the `.about-*` keyframe classes in global.css.
 */
import zioIcon from '../assets/zio-icon.png';

interface Props {
  page: 'sayzio' | 'zio';
  onNavigate: (url: string) => void;
}

const SAYZIO_SITE = 'https://1in.me';

// ── Small building blocks ────────────────────────────────────────────────────

function Orbs() {
  return (
    <>
      <div className="about-orb" style={{ top: '-120px', left: '-100px', width: 380, height: 380, background: 'radial-gradient(circle, rgba(59,130,246,0.35), transparent 70%)' }} />
      <div className="about-orb about-orb-slow" style={{ bottom: '-140px', right: '-80px', width: 420, height: 420, background: 'radial-gradient(circle, rgba(14,165,233,0.28), transparent 70%)' }} />
      <div className="about-orb about-orb-slower" style={{ top: '30%', right: '15%', width: 260, height: 260, background: 'radial-gradient(circle, rgba(99,102,241,0.22), transparent 70%)' }} />
    </>
  );
}

function Hero({ title, subtitle, delayBase = 0 }: { title: string; subtitle: string; delayBase?: number }) {
  return (
    <div style={{ textAlign: 'center', position: 'relative', zIndex: 1 }}>
      <div className="about-logo-wrap about-rise" style={{ animationDelay: `${delayBase}ms` }}>
        <div className="about-logo-ring" />
        <div className="about-logo-ring about-logo-ring-2" />
        <img src={zioIcon} alt="Zio" width={88} height={88} style={{ borderRadius: 22, position: 'relative', zIndex: 1, boxShadow: '0 8px 40px rgba(59,130,246,0.45)' }} />
      </div>
      <h1 className="about-rise" style={{ animationDelay: `${delayBase + 120}ms`, fontSize: 40, fontWeight: 700, letterSpacing: -1, margin: '28px 0 10px', background: 'linear-gradient(90deg, #93c5fd, #e2e8f0, #7dd3fc)', WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }}>
        {title}
      </h1>
      <p className="about-rise" style={{ animationDelay: `${delayBase + 240}ms`, fontSize: 16, color: 'var(--color-text-muted)', maxWidth: 560, margin: '0 auto', lineHeight: 1.6 }}>
        {subtitle}
      </p>
    </div>
  );
}

function Card({ icon, title, text, delay }: { icon: string; title: string; text: string; delay: number }) {
  return (
    <div className="about-card about-rise" style={{ animationDelay: `${delay}ms` }}>
      <div style={{ fontSize: 26, marginBottom: 10 }}>{icon}</div>
      <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 6, color: 'var(--color-text)' }}>{title}</div>
      <div style={{ fontSize: 12.5, color: 'var(--color-text-muted)', lineHeight: 1.55 }}>{text}</div>
    </div>
  );
}

function CtaButton({ label, onClick, primary = false, delay }: { label: string; onClick: () => void; primary?: boolean; delay: number }) {
  return (
    <button
      className="about-cta about-rise"
      onClick={onClick}
      style={{
        animationDelay: `${delay}ms`,
        padding: '10px 22px',
        borderRadius: 10,
        fontSize: 13.5,
        fontWeight: 600,
        cursor: 'pointer',
        border: primary ? 'none' : '1px solid var(--color-border)',
        background: primary ? 'linear-gradient(90deg, #3d6bff, #8b5cf6)' : 'var(--color-bg-elevated)',
        color: primary ? '#fff' : 'var(--color-text)',
        boxShadow: primary ? '0 4px 20px rgba(37,99,235,0.4)' : 'none',
      }}
    >
      {label}
    </button>
  );
}

// ── Pages ────────────────────────────────────────────────────────────────────

const SAYZIO_CARDS = [
  { icon: '🔗', title: 'Smart links', text: 'Short links, QR codes, and biolinks — mini-websites that hold everything you share, all in one place.' },
  { icon: '🎨', title: 'Your brand, everywhere', text: 'Themes, colors, and layouts you control, so every link and page looks unmistakably yours.' },
  { icon: '📊', title: 'Know what works', text: 'See clicks, visitors, and where they come from with clear, friendly analytics and maps.' },
  { icon: '🤖', title: 'AI that builds with you', text: 'Describe what you want and Sayzio AI drafts pages, writes copy, and keeps everything on-brand.' },
  { icon: '💬', title: 'Reach your people', text: 'Collect subscribers, take orders, run forms and events — your audience is always one tap away.' },
  { icon: '🛡️', title: 'Safe and fair', text: 'Your data belongs to you. Clear pricing, no surprises, and privacy built into every feature.' },
];

const ZIO_CARDS = [
  { icon: '🕵️', title: 'Privacy first', text: 'Private windows leave no trace, trackers are kept out, and your browsing data stays on your device.' },
  { icon: '⚡', title: 'Built-in Zio AI', text: 'Summarize any page, ask questions, and get help right in the sidebar — no extra tabs needed.' },
  { icon: '🧰', title: 'Sayzio superpowers', text: 'Shorten the page you are on, make a QR code, or add it to your biolink in one right-click.' },
  { icon: '🗂️', title: 'Workspaces', text: 'Separate profiles keep work and personal browsing apart — each with its own logins and history.' },
  { icon: '📱', title: 'Device Lab', text: 'Preview any website the way it looks on phones and tablets, side by side, while you build.' },
  { icon: '🚀', title: 'Fast and focused', text: 'A clean, modern browser that stays out of your way and puts your content front and center.' },
];

export function AboutPage({ page, onNavigate }: Props) {
  const isSayzio = page === 'sayzio';
  const cards = isSayzio ? SAYZIO_CARDS : ZIO_CARDS;

  return (
    <div className="about-page" style={{
      flex: 1,
      overflowY: 'auto',
      overflowX: 'hidden',
      position: 'relative',
      background: 'radial-gradient(1200px 600px at 50% -10%, rgba(37,99,235,0.12), transparent), var(--color-bg, #101014)',
    }}>
      <Orbs />
      <div style={{ maxWidth: 860, margin: '0 auto', padding: '64px 32px 80px', position: 'relative', zIndex: 1 }}>
        {isSayzio ? (
          <Hero
            title="About Sayzio"
            subtitle="Sayzio is the home for everything you share online. Create beautiful links, biolinks, and QR codes, understand your audience, and grow your brand — all from one place."
          />
        ) : (
          <Hero
            title="About Zio Browser"
            subtitle="Zio Browser is Sayzio's privacy-first browser with a built-in AI assistant. Browse, create, and stay in control — your data stays yours."
          />
        )}

        {/* Feature cards */}
        <div style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(230px, 1fr))',
          gap: 14,
          marginTop: 48,
        }}>
          {cards.map((c, i) => (
            <Card key={c.title} icon={c.icon} title={c.title} text={c.text} delay={380 + i * 90} />
          ))}
        </div>

        {/* CTA row */}
        <div style={{ display: 'flex', gap: 12, justifyContent: 'center', marginTop: 48, flexWrap: 'wrap' }}>
          {isSayzio ? (
            <>
              <CtaButton primary label="Visit Sayzio" onClick={() => onNavigate(SAYZIO_SITE)} delay={1000} />
              <CtaButton label="About Zio Browser →" onClick={() => onNavigate('about:zio')} delay={1080} />
            </>
          ) : (
            <>
              <CtaButton primary label="Explore Sayzio" onClick={() => onNavigate(SAYZIO_SITE)} delay={1000} />
              <CtaButton label="About Sayzio →" onClick={() => onNavigate('about:sayzio')} delay={1080} />
            </>
          )}
        </div>

        <div className="about-rise" style={{ animationDelay: '1200ms', textAlign: 'center', marginTop: 56, fontSize: 11.5, color: 'var(--color-text-muted)', opacity: 0.7 }}>
          {isSayzio
            ? 'Sayzio — say it once, share it everywhere.'
            : 'Zio Browser — a Sayzio product. Private by design.'}
        </div>
      </div>
    </div>
  );
}
