import { Link } from "wouter";
import { Instagram, Facebook, Youtube, Linkedin, Twitter } from "lucide-react";
import { LOGIN_URL, SIGNUP_URL, SOCIAL_LINKS, type SocialLink } from "@/config";
import { Button } from "@/components/ui/button";
import { BrandLogo } from "@/components/layout/brand-logo";

function ThreadsIcon({ className }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 24 24"
      className={className}
      fill="currentColor"
      aria-hidden="true"
      focusable="false"
    >
      <path d="M12.186 24h-.007c-3.581-.024-6.334-1.205-8.184-3.509C2.35 18.44 1.5 15.586 1.472 12.01v-.017c.03-3.579.879-6.43 2.525-8.482C5.845 1.205 8.6.024 12.18 0h.014c2.746.02 5.043.725 6.826 2.098 1.677 1.291 2.858 3.13 3.509 5.467l-2.04.569c-1.104-3.96-3.898-5.984-8.304-6.015-2.91.022-5.11.936-6.54 2.717C4.307 6.504 3.616 8.914 3.589 12c.027 3.086.718 5.496 2.057 7.164 1.43 1.781 3.631 2.695 6.54 2.717 2.623-.02 4.358-.631 5.8-2.045 1.647-1.613 1.618-3.593 1.09-4.798-.31-.71-.873-1.3-1.634-1.75-.192 1.352-.622 2.446-1.284 3.272-.886 1.102-2.14 1.704-3.73 1.79-1.202.065-2.361-.218-3.259-.801-1.063-.689-1.685-1.74-1.752-2.964-.065-1.19.408-2.285 1.33-3.082.88-.76 2.119-1.207 3.583-1.291a13.853 13.853 0 0 1 3.02.142c-.126-.742-.375-1.332-.744-1.758-.508-.586-1.292-.885-2.328-.892h-.035c-.836 0-1.973.228-2.7 1.388L7.49 7.794c.974-1.552 2.56-2.405 4.61-2.405h.05c3.43.022 5.474 2.146 5.678 5.853.117.05.232.103.346.158 1.604.755 2.778 1.896 3.395 3.301.86 1.952.94 5.135-1.64 7.67-1.971 1.94-4.367 2.797-7.667 2.797Zm1.866-12.55c-.343 0-.692.01-1.046.034-1.879.114-3.048.991-2.983 2.235.067 1.293 1.493 1.894 2.864 1.821 1.262-.067 2.913-.557 3.193-3.788a10.42 10.42 0 0 0-2.028-.302Z" />
    </svg>
  );
}

function SocialIcon({ icon, className }: { icon: SocialLink["icon"]; className?: string }) {
  switch (icon) {
    case "instagram":
      return <Instagram className={className} aria-hidden="true" />;
    case "threads":
      return <ThreadsIcon className={className} />;
    case "x":
      return <Twitter className={className} aria-hidden="true" />;
    case "facebook":
      return <Facebook className={className} aria-hidden="true" />;
    case "youtube":
      return <Youtube className={className} aria-hidden="true" />;
    case "linkedin":
      return <Linkedin className={className} aria-hidden="true" />;
  }
}

interface FooterLink {
  href: string;
  label: string;
  external?: boolean;
}

interface FooterColumn {
  heading: string;
  links: FooterLink[];
}

const columns: FooterColumn[] = [
  {
    heading: "Product",
    links: [
      { href: "/features", label: "Features" },
      { href: "/how-it-works", label: "How it works" },
      { href: "/analytics", label: "Analytics" },
      { href: "/integrations", label: "Integrations" },
      { href: "/domains", label: "Domains & links" },
      { href: "/workspace-team", label: "Workspace & team" },
      { href: "/pricing", label: "Pricing" },
    ],
  },
  {
    heading: "AI Suite",
    links: [
      { href: "/ai/ai-chatbot", label: "AI Chatbot" },
      { href: "/ai/ai-agent", label: "AI Agent" },
      { href: "/ai/ai-widget", label: "AI Widget" },
      { href: "/ai/ai-voice-assistant", label: "AI Voice Assistant" },
      { href: "/resume-builder", label: "Resume builder" },
      { href: "/api-docs", label: "API & developers" },
    ],
  },
  {
    heading: "Sayzio for…",
    links: [
      { href: "/for/creators", label: "Creators" },
      { href: "/for/agencies", label: "Agencies" },
      { href: "/for/coaches", label: "Coaches" },
      { href: "/for/musicians", label: "Musicians" },
      { href: "/for/small-business", label: "Small business" },
      { href: "/services", label: "All use cases" },
    ],
  },
  {
    heading: "Resources",
    links: [
      { href: "/compare", label: "Compare" },
      { href: "/discovery", label: "Discovery" },
      { href: "/creators-feed", label: "Creators feed" },
      { href: "/blog", label: "Blog" },
      { href: "/changelog", label: "Changelog" },
      { href: "/faq", label: "FAQ" },
    ],
  },
  {
    heading: "Company",
    links: [
      { href: "/about", label: "About us" },
      { href: "/contact", label: "Contact" },
      { href: "/premium-features", label: "Premium" },
      { href: "/buzz", label: "Social proof" },
    ],
  },
  {
    heading: "Legal",
    links: [
      { href: "/terms", label: "Terms of Service" },
      { href: "/privacy", label: "Privacy Policy" },
      { href: "/refunds", label: "Refund Policy" },
      { href: "/gdpr", label: "GDPR" },
      { href: "/cookies", label: "Cookie Policy" },
    ],
  },
];

export function Footer() {
  return (
    <footer className="border-t bg-card/50 mt-24">
      <div className="container mx-auto px-6 py-16">
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-10 mb-16">
          <div className="col-span-2 lg:col-span-2">
            <Link href="/" className="inline-flex items-center mb-4" aria-label="Sayzio home">
              <BrandLogo imgHeight={32} textClassName="text-2xl font-bold text-primary" />
            </Link>
            <p className="text-muted-foreground mb-6 max-w-sm">
              One link to everything. The all-in-one link, monetization &amp; growth stack —
              Link in Bio pages, short links, QR codes, analytics and AI, free forever.
            </p>
            <div className="flex flex-wrap gap-3">
              <Button asChild>
                <a href={SIGNUP_URL}>Get started for free</a>
              </Button>
              <Button asChild variant="secondary">
                <a href={LOGIN_URL}>Log in</a>
              </Button>
            </div>
            <div className="flex flex-wrap items-center gap-4 mt-6">
              {SOCIAL_LINKS.map((social) => (
                <a
                  key={social.label}
                  href={social.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label={`Sayzio on ${social.label}`}
                  title={`${social.label} · ${social.handle}`}
                  className="text-muted-foreground hover:text-primary transition-colors"
                >
                  <SocialIcon icon={social.icon} className="h-5 w-5" />
                </a>
              ))}
            </div>
          </div>

          {columns.map((col) => (
            <div key={col.heading}>
              <h4 className="font-semibold mb-4 text-foreground text-sm">{col.heading}</h4>
              <ul className="space-y-3">
                {col.links.map((link) => (
                  <li key={link.href + link.label}>
                    {link.external ? (
                      <a
                        href={link.href}
                        className="text-muted-foreground hover:text-primary transition-colors text-sm"
                      >
                        {link.label}
                      </a>
                    ) : (
                      <Link
                        href={link.href}
                        className="text-muted-foreground hover:text-primary transition-colors text-sm"
                      >
                        {link.label}
                      </Link>
                    )}
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        <div className="pt-8 border-t border-border flex flex-col md:flex-row justify-between items-center gap-4">
          <p className="text-sm text-muted-foreground">
            © {new Date().getFullYear()} Sayzio. All rights reserved.
          </p>
          <div className="flex gap-4 text-sm text-muted-foreground">
            Built for creators, coaches, freelancers, agencies, and businesses.
          </div>
        </div>
      </div>
    </footer>
  );
}
