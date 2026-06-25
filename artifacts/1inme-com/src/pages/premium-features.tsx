import { Link } from "wouter";
import { PageLayout } from "@/components/layout/page-layout";
import {
  MarketingHero,
  SectionHeading,
  FeatureGrid,
  CTABand,
} from "@/components/marketing/marketing";
import { SIGNUP_URL, PRICING_URL } from "@/config";
import { Globe, Palette, Bot, BarChart3, Code2, Users } from "lucide-react";

const items = [
  { icon: Globe, name: "Custom domains & SSL", description: "Bring your own domain or subdomain with a free SSL certificate provisioned automatically — your brand on every link." },
  { icon: Palette, name: "Custom branding & CSS/JS", description: "Remove Sayzio branding, add your favicon, and inject custom CSS and JavaScript to make every page unmistakably yours." },
  { icon: Bot, name: "The full AI Suite", description: "Unlock the AI Chatbot, AI Agent, AI Widget and AI Voice Assistant — they answer, qualify and book for you 24/7." },
  { icon: BarChart3, name: "Deeper analytics", description: "Longer retention, heatmaps, per-block CTR and exports — plus the AI Performance Coach surfacing one-click fixes." },
  { icon: Code2, name: "Open API & webhooks", description: "Higher API allowances, webhooks for every event, and full mobile parity so you can build on top of Sayzio." },
  { icon: Users, name: "Teams & workspaces", description: "More seats, more workspaces, granular roles and a shared brand kit so your whole team ships on brand." },
];

export default function PremiumFeatures() {
  return (
    <PageLayout
      title="Premium features"
      description="Everything you unlock on paid plans — custom domains, full AI Suite, deeper analytics, custom branding, the open API and team workspaces."
    >
      <MarketingHero
        eyebrow="Premium"
        title="Unlock the whole"
        highlight="growth stack."
        subtitle="Custom domains, the full AI Suite, deeper analytics, custom branding and team workspaces — everything you unlock when you upgrade."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "Compare all plans", href: PRICING_URL }}
      />

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="What you unlock" title="Premium, without the bloat." />
          <FeatureGrid items={items} />
          <div className="text-center mt-10">
            <Link href="/features" className="text-sm font-semibold text-primary hover:underline">
              See every feature →
            </Link>
          </div>
        </div>
      </section>

      <CTABand
        title="Ready to go premium?"
        subtitle="Start free, upgrade only when you outgrow it. No surprise seat charges or overages."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "Compare all plans", href: PRICING_URL }}
      />
    </PageLayout>
  );
}
