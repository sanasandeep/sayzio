import { Link } from "wouter";
import { LOGIN_URL, SIGNUP_URL } from "@/config";
import { Button } from "@/components/ui/button";

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
    heading: "1INME for…",
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
            <Link href="/" className="text-2xl font-bold text-primary mb-4 block">
              1INME
            </Link>
            <p className="text-muted-foreground mb-6 max-w-sm">
              One link to everything. The all-in-one link, monetization &amp; growth stack —
              biolinks, short links, QR codes, analytics and AI, free forever.
            </p>
            <div className="flex flex-wrap gap-3">
              <Button asChild>
                <a href={SIGNUP_URL}>Get started for free</a>
              </Button>
              <Button asChild variant="secondary">
                <a href={LOGIN_URL}>Log in</a>
              </Button>
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
            © {new Date().getFullYear()} 1INME. All rights reserved.
          </p>
          <div className="flex gap-4 text-sm text-muted-foreground">
            Built for creators, coaches, freelancers, agencies, and businesses.
          </div>
        </div>
      </div>
    </footer>
  );
}
