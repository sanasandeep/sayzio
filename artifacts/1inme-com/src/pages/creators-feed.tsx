import { PageLayout } from "@/components/layout/page-layout";
import {
  MarketingHero,
  SectionHeading,
  FeatureGrid,
  CTABand,
  StatRow,
} from "@/components/marketing/marketing";
import { SIGNUP_URL, PRICING_URL } from "@/config";
import { Rss, Pin, Users } from "lucide-react";

const items = [
  { icon: Rss, name: "Fresh from the community", description: "See what creators on 1INME are posting right now — product drops, behind-the-scenes notes, announcements and updates from people building their audience here. Scroll, discover and follow your favourites in one tap." },
  { icon: Pin, name: "How posts get here", description: "Any creator with a public biolink can publish posts and have them surface in this feed. Pinned posts from staff or partners may appear at the top; everything else is ordered newest-first so you always see what just dropped." },
  { icon: Users, name: "Build your following", description: "Posting from your biolink is the easiest way to keep your audience warm between launches. Visitors can follow you straight from the post and they'll see your next one in their feed." },
];

export default function CreatorsFeed() {
  return (
    <PageLayout
      title="Creators feed"
      description="The latest posts from creators on 1INME — updates, drops, news and behind-the-scenes from people building in public."
    >
      <MarketingHero
        eyebrow="Creators feed"
        title="Fresh from the"
        highlight="community."
        subtitle="The latest posts from creators on 1INME — updates, drops, news and behind-the-scenes from people building in public."
        primary={{ label: "Start posting free", href: SIGNUP_URL }}
        secondary={{ label: "See pricing", href: PRICING_URL }}
      >
        <StatRow
          stats={[
            { value: "Newest-first", label: "Always fresh" },
            { value: "Live", label: "Updated continuously" },
            { value: "1-tap", label: "Follow creators" },
          ]}
        />
      </MarketingHero>

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="How the feed works" title="Your audience, warm between launches." />
          <FeatureGrid items={items} columns={3} />
        </div>
      </section>

      <CTABand
        title="Keep your audience coming back."
        subtitle="Free forever, no credit card. Post from your biolink and grow your following."
        primary={{ label: "Start posting free", href: SIGNUP_URL }}
        secondary={{ label: "Compare all plans", href: PRICING_URL }}
      />
    </PageLayout>
  );
}
