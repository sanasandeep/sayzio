import { PageLayout } from "@/components/layout/page-layout";
import {
  MarketingHero,
  SectionHeading,
  FeatureGrid,
  CTABand,
} from "@/components/marketing/marketing";
import { SIGNUP_URL, PRICING_URL } from "@/config";
import { Instagram, Music2, Facebook, Twitter, Linkedin, Repeat, Activity, Webhook } from "lucide-react";

const networks = [
  { icon: Instagram, name: "Instagram", description: "Connect in one tap to pull profile, posts and follower counts into your Link in Bio." },
  { icon: Music2, name: "TikTok", description: "Plug in TikTok to surface your latest videos and follower count." },
  { icon: Facebook, name: "Facebook page", description: "Hook up a Facebook page so visitors can follow and you can pull recent posts." },
  { icon: Twitter, name: "X (Twitter)", description: "Keep your latest posts and follower count live on your Link in Bio." },
  { icon: Linkedin, name: "LinkedIn", description: "Plug in your profile or company page for a one-tap follow surface." },
  { icon: Music2, name: "Pinterest", description: "Connect Pinterest to surface your boards and pins on your Link in Bio." },
];

const reliability = [
  { icon: Repeat, name: "Auto-retry on broken connections", description: "When a token expires we keep retrying with smart back-off and only ping you when we actually need you to reconnect." },
  { icon: Activity, name: "Connection health dashboard", description: "See healthy / needs reconnect / paused for every network at a glance, with last-synced timestamps." },
  { icon: Webhook, name: "Pixels, webhooks & Zapier", description: "Drop in Facebook, Google, LinkedIn, TikTok and Pinterest pixels, fire webhooks on clicks, and connect to thousands of apps via Zapier." },
];

export default function Integrations() {
  return (
    <PageLayout
      title="Integrations"
      description="One-click connections to every network you live on — with auto-retry, live status and notifications when something needs your attention."
    >
      <MarketingHero
        eyebrow="Integrations"
        title="Connect every network you"
        highlight="live on."
        subtitle="One-click connections to the platforms that matter, with auto-retry, live status, and notifications when something needs your attention."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "See pricing", href: PRICING_URL }}
      />

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="Social networks" title="One tap to connect." />
          <FeatureGrid items={networks} />
        </div>
      </section>

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="Always connected" title="Reliable plumbing you never think about." />
          <FeatureGrid items={reliability} columns={3} />
        </div>
      </section>

      <CTABand
        title="Plug Sayzio into your whole stack."
        subtitle="Free forever, no credit card. Connect your networks and tools in seconds."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "Compare all plans", href: PRICING_URL }}
      />
    </PageLayout>
  );
}
