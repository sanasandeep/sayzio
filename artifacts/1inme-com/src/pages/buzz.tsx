import { PageLayout } from "@/components/layout/page-layout";
import {
  MarketingHero,
  SectionHeading,
  FeatureGrid,
  CTABand,
  StatRow,
} from "@/components/marketing/marketing";
import { SIGNUP_URL, PRICING_URL } from "@/config";
import { Bell, Eye, ShoppingBag, MousePointerClick, Star, ShieldCheck } from "lucide-react";

const items = [
  { icon: Bell, name: "Floating activity notifications", description: "Small pop-up cards surface recent visitors, signups and purchases to nudge new visitors to take action." },
  { icon: Eye, name: "Live visitor counts", description: "Show how many people are looking right now, so newcomers feel the momentum the moment they land." },
  { icon: ShoppingBag, name: "Recent purchases & signups", description: "Real, recent activity proves people are already buying and joining — the strongest nudge there is." },
  { icon: MousePointerClick, name: "Targeting rules", description: "Choose where, when and to whom each notification shows, with full control over frequency and timing." },
  { icon: Star, name: "Reviews & ratings", description: "Pull in star ratings and testimonials so first-time visitors trust you in seconds." },
  { icon: ShieldCheck, name: "Privacy-respecting by design", description: "Activity is anonymised and aggregated — social proof that builds trust without exposing anyone." },
];

export default function Buzz() {
  return (
    <PageLayout
      title="Buzz — social proof widgets"
      description="Build trust on your biolink by showing real activity from real visitors as it happens — recent signups, purchases, live counts and reviews."
    >
      <MarketingHero
        eyebrow="Social proof"
        title="People are"
        highlight="talking."
        subtitle="Build trust on your biolink by showing real activity from real visitors as it happens — recent signups, purchases, live visitor counts and reviews."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "See pricing", href: PRICING_URL }}
      >
        <StatRow
          stats={[
            { value: "40+", label: "Press features" },
            { value: "9", label: "Awards" },
            { value: "4.9/5", label: "Customer rating" },
          ]}
        />
      </MarketingHero>

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="What you get" title="Turn real activity into trust." />
          <FeatureGrid items={items} />
        </div>
      </section>

      <CTABand
        title="Let the buzz do the convincing."
        subtitle="Free forever, no credit card. Add social proof widgets to your page in minutes."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "Compare all plans", href: PRICING_URL }}
      />
    </PageLayout>
  );
}
