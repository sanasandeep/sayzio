import { PageLayout } from "@/components/layout/page-layout";
import {
  MarketingHero,
  SectionHeading,
  FeatureGrid,
  CTABand,
  StatRow,
} from "@/components/marketing/marketing";
import { SIGNUP_URL, PRICING_URL } from "@/config";
import { Map, Flame, Brain, Download, Eye, ShieldCheck } from "lucide-react";

const items = [
  { icon: Map, name: "Live visitor map", description: "Watch visitors arrive in real time, with pins on a world map showing exactly where your audience is right now." },
  { icon: Flame, name: "Click heatmaps", description: "See which blocks on your Link in Bio visitors actually click — and where they drop off — so you can prune dead weight." },
  { icon: Brain, name: "AI Performance Coach", description: "It watches your live numbers, compares against best practice, and surfaces a small prioritised list of one-click fixes." },
  { icon: Download, name: "CSV & JSON export", description: "Export clicks, sessions and conversions, plus a webhook stream that feeds your real-time pipelines." },
  { icon: Eye, name: "Per-block CTR", description: "Every block reports its own clicks, view rate and click-through so you know what's working at a glance." },
  { icon: ShieldCheck, name: "Privacy-first by default", description: "Cookieless, no third-party scripts, and Do Not Track honored — visitors counted anonymously, no fingerprints." },
];

export default function Analytics() {
  return (
    <PageLayout
      title="Analytics & AI Coach"
      description="Numbers that move — live visitor maps, click heatmaps, per-block CTR and an AI Performance Coach that turns data into one-tap fixes."
    >
      <MarketingHero
        eyebrow="Analytics"
        title="See what works,"
        highlight="fix what doesn't."
        subtitle="Live analytics on every link and page — plus an AI Performance Coach that turns your numbers into a short, prioritised list of one-click fixes."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "See pricing", href: PRICING_URL }}
      >
        <StatRow
          stats={[
            { value: "247", label: "Live visitors" },
            { value: "87", label: "Coach health" },
            { value: "1.4k", label: "QR scans" },
            { value: "Real-time", label: "Always on" },
          ]}
        />
      </MarketingHero>

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="What you get" title="Analytics that tell you what to do next." />
          <FeatureGrid items={items} />
        </div>
      </section>

      <CTABand
        title="Turn your numbers into action."
        subtitle="Free forever, no credit card. Live analytics and the AI Coach are on from day one."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "Compare all plans", href: PRICING_URL }}
      />
    </PageLayout>
  );
}
