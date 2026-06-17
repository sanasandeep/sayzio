import { useParams, Link } from "wouter";
import { PageLayout } from "@/components/layout/page-layout";
import {
  MarketingHero,
  SectionHeading,
  CheckList,
  CTABand,
} from "@/components/marketing/marketing";
import { SIGNUP_URL, PRICING_URL } from "@/config";
import { getCompetitor, competitors, migrationSteps } from "@/content/compare";
import { ArrowRight } from "lucide-react";
import NotFound from "@/pages/not-found";

export default function CompareDetail() {
  const params = useParams();
  const competitor = getCompetitor(params.slug ?? "");

  if (!competitor) return <NotFound />;

  const others = competitors.filter((c) => c.slug !== competitor.slug);

  return (
    <PageLayout
      title={`1INME vs ${competitor.name}`}
      description={competitor.intro}
    >
      <MarketingHero
        eyebrow={`1INME vs ${competitor.name}`}
        title={competitor.headline}
        subtitle={competitor.intro}
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "See pricing", href: PRICING_URL }}
      />

      <section className="py-12">
        <div className="container mx-auto px-6">
          <div className="grid md:grid-cols-2 gap-6 max-w-5xl mx-auto">
            <div className="glass-card rounded-3xl p-8 border-primary/30">
              <div className="text-xs font-bold uppercase tracking-wider text-primary mb-1">
                {competitor.badge}
              </div>
              <h3 className="text-xl font-bold mb-5">Where 1INME wins</h3>
              <CheckList items={competitor.ourWins} />
            </div>
            <div className="glass-card rounded-3xl p-8">
              <div className="text-xs font-bold uppercase tracking-wider text-muted-foreground mb-1">
                Fair is fair
              </div>
              <h3 className="text-xl font-bold mb-5">Where {competitor.name} is strong</h3>
              <CheckList items={competitor.theirWins} />
            </div>
          </div>
        </div>
      </section>

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading
            eyebrow="Switching is easy"
            title={`Move from ${competitor.name} in three steps.`}
          />
          <div className="grid sm:grid-cols-3 gap-6 max-w-4xl mx-auto">
            {migrationSteps.map((step, i) => (
              <div key={step.title} className="glass-card rounded-3xl p-7">
                <div className="w-9 h-9 rounded-full bg-primary text-primary-foreground flex items-center justify-center font-bold mb-4">
                  {i + 1}
                </div>
                <h3 className="font-semibold mb-2">{step.title}</h3>
                <p className="text-sm text-muted-foreground">{step.body}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="More comparisons" title="See how 1INME stacks up elsewhere." />
          <div className="flex flex-wrap justify-center gap-3 max-w-4xl mx-auto">
            {others.map((c) => (
              <Link
                key={c.slug}
                href={`/compare/${c.slug}`}
                className="glass-card rounded-full px-5 py-2.5 text-sm font-medium hover:border-primary/40 transition-colors inline-flex items-center gap-1"
              >
                vs {c.name} <ArrowRight className="w-4 h-4" />
              </Link>
            ))}
            <Link
              href="/compare"
              className="glass-card rounded-full px-5 py-2.5 text-sm font-medium text-primary hover:border-primary/40 transition-colors inline-flex items-center gap-1"
            >
              Full feature matrix <ArrowRight className="w-4 h-4" />
            </Link>
          </div>
        </div>
      </section>

      <CTABand
        title={`Ready to leave ${competitor.name} behind?`}
        subtitle="Free forever, no credit card. The whole growth stack behind one link."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "Compare all plans", href: PRICING_URL }}
      />
    </PageLayout>
  );
}
