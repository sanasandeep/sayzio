import { PageLayout } from "@/components/layout/page-layout";
import {
  MarketingHero,
  SectionHeading,
  FeatureGrid,
  CheckList,
  CTABand,
} from "@/components/marketing/marketing";
import { SIGNUP_URL, PRICING_URL } from "@/config";
import { Terminal, KeyRound, Webhook, Gauge, BookOpen, Boxes } from "lucide-react";

const items = [
  { icon: Terminal, name: "REST API", description: "A clean, predictable REST API with a unified response envelope for links, biolinks, QR codes, analytics and more." },
  { icon: KeyRound, name: "Bearer-token auth", description: "Generate scoped API keys and authenticate with a simple bearer token — no OAuth dance required." },
  { icon: Webhook, name: "Webhooks", description: "Subscribe to clicks, scans, subscribes and conversions, and stream events to your own pipeline in real time." },
  { icon: Gauge, name: "Usage metering", description: "Monthly call allowances per plan with clear, proactive warnings as you approach your limit — no surprise cut-offs." },
  { icon: BookOpen, name: "Docs & examples", description: "Copy-paste examples for every endpoint, with predictable errors and validation messages that tell you exactly what's wrong." },
  { icon: Boxes, name: "Mobile SDK parity", description: "Everything the apps can do, your code can do too — full parity between the API and the official mobile apps." },
];

const endpoints = [
  "POST /api/v1/auth/login — exchange credentials for a token",
  "GET /api/v1/links — list and filter your links",
  "POST /api/v1/qr-codes — generate styled QR codes",
  "GET /api/v1/biolinks/{handle} — resolve a public biolink",
  "GET /api/v1/feed — the follow/subscribe-aware feed",
];

export default function ApiDocs() {
  return (
    <PageLayout
      title="Developer API"
      description="Build on top of Sayzio — a clean REST API with bearer-token auth, webhooks, usage metering and full mobile parity."
    >
      <MarketingHero
        eyebrow="Developers"
        title="Build on top of"
        highlight="Sayzio."
        subtitle="A clean, predictable REST API with bearer-token auth, webhooks and usage metering — everything the apps can do, your code can do too."
        primary={{ label: "Get an API key", href: SIGNUP_URL }}
        secondary={{ label: "See pricing", href: PRICING_URL }}
      />

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="What you get" title="Developer-first by design." />
          <FeatureGrid items={items} />
        </div>
      </section>

      <section className="py-12">
        <div className="container mx-auto px-6">
          <div className="glass-card rounded-3xl p-8 lg:p-12 max-w-4xl mx-auto">
            <SectionHeading eyebrow="Endpoints" title="A taste of the API." center={false} />
            <CheckList items={endpoints} />
          </div>
        </div>
      </section>

      <CTABand
        title="Ship something on Sayzio."
        subtitle="Free forever to start. Generate a key and make your first call in minutes."
        primary={{ label: "Get an API key", href: SIGNUP_URL }}
        secondary={{ label: "Compare all plans", href: PRICING_URL }}
      />
    </PageLayout>
  );
}
