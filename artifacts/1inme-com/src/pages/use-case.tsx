import { useParams, Link } from "wouter";
import { PageLayout } from "@/components/layout/page-layout";
import {
  MarketingHero,
  SectionHeading,
  FeatureGrid,
  CheckList,
  CTABand,
  FaqAccordion,
} from "@/components/marketing/marketing";
import { SIGNUP_URL, PRICING_URL } from "@/config";
import { getUseCase, useCases } from "@/content/use-cases";
import { Sparkles } from "lucide-react";
import NotFound from "@/pages/not-found";

export default function UseCase() {
  const params = useParams();
  const useCase = getUseCase(params.slug ?? "");

  if (!useCase) return <NotFound />;

  const others = useCases.filter((u) => u.slug !== useCase.slug);

  return (
    <PageLayout title={useCase.title} description={useCase.description}>
      <MarketingHero
        eyebrow={useCase.eyebrow}
        title={useCase.tagline}
        subtitle={useCase.description}
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "See pricing", href: PRICING_URL }}
      />

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="Why Sayzio" title="Everything this job needs, in one link." />
          <FeatureGrid
            items={useCase.sections.map((s) => ({ name: s.heading, description: s.body }))}
          />
        </div>
      </section>

      <section className="py-12">
        <div className="container mx-auto px-6">
          <div className="glass-card rounded-3xl p-8 lg:p-12 max-w-4xl mx-auto">
            <SectionHeading eyebrow="Featured tools" title="The features you'll reach for most." center={false} />
            <div className="grid sm:grid-cols-2 gap-x-10 gap-y-4">
              <CheckList items={useCase.features.slice(0, Math.ceil(useCase.features.length / 2))} />
              <CheckList items={useCase.features.slice(Math.ceil(useCase.features.length / 2))} />
            </div>
            <div className="mt-8">
              <Link href="/features" className="text-sm font-semibold text-primary hover:underline">
                Explore all features →
              </Link>
            </div>
          </div>
        </div>
      </section>

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="FAQ" title="Questions, answered." />
          <FaqAccordion items={useCase.faqs} />
        </div>
      </section>

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="More use cases" title="Sayzio works for every kind of work." />
          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-5xl mx-auto">
            {others.map((u) => (
              <Link
                key={u.slug}
                href={`/for/${u.slug}`}
                className="glass-card rounded-3xl p-6 hover:border-primary/40 transition-colors"
              >
                <div className="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center mb-4">
                  <Sparkles className="w-5 h-5" />
                </div>
                <div className="font-semibold mb-1">{u.eyebrow}</div>
                <div className="text-sm text-muted-foreground">{u.navDesc}</div>
              </Link>
            ))}
          </div>
        </div>
      </section>

      <CTABand
        title="Ready to make it yours?"
        subtitle="Build your page, share the link, and watch it work. Free forever — no credit card."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "Compare all plans", href: PRICING_URL }}
      />
    </PageLayout>
  );
}
