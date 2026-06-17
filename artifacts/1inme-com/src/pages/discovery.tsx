import { PageLayout } from "@/components/layout/page-layout";
import {
  MarketingHero,
  SectionHeading,
  FeatureGrid,
  CTABand,
  StatRow,
} from "@/components/marketing/marketing";
import { SIGNUP_URL, PRICING_URL } from "@/config";
import { Search, Sparkles, ToggleRight } from "lucide-react";

const items = [
  { icon: Search, name: "Find your next favourite link", description: "Browse the latest public biolink pages on 1INME. Search by name, handle or topic, tap any card to open the page, and follow the creators whose work you love so you never miss a new post or drop." },
  { icon: Sparkles, name: "Curated, not crowded", description: "Only pages whose creators have opted in to be discoverable show up here. That keeps the directory genuine — the people listed actually want new visitors and are actively keeping their pages fresh." },
  { icon: ToggleRight, name: "Want to be listed?", description: "Toggle \"Show me in Discover\" from your profile settings and your public biolink will appear here within a few minutes. You stay in control: turn it off any time and you disappear from the directory." },
];

export default function Discovery() {
  return (
    <PageLayout
      title="Discover biolinks"
      description="Browse public 1INME biolink pages — find creators, brands and businesses sharing their work."
    >
      <MarketingHero
        eyebrow="Discover"
        title="Find creators worth"
        highlight="following."
        subtitle="Browse public 1INME biolink pages — find creators, brands and businesses sharing their work, and follow the ones you love."
        primary={{ label: "Get listed free", href: SIGNUP_URL }}
        secondary={{ label: "See pricing", href: PRICING_URL }}
      >
        <StatRow
          stats={[
            { value: "Curated", label: "Opt-in directory" },
            { value: "120k+", label: "Followers" },
            { value: "Fresh", label: "Updated daily" },
          ]}
        />
      </MarketingHero>

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="How Discover works" title="A genuine directory, not a feed dump." />
          <FeatureGrid items={items} columns={3} />
        </div>
      </section>

      <CTABand
        title="Get found by new visitors."
        subtitle="Free forever, no credit card. Opt into Discover and appear here within minutes."
        primary={{ label: "Get listed free", href: SIGNUP_URL }}
        secondary={{ label: "Compare all plans", href: PRICING_URL }}
      />
    </PageLayout>
  );
}
