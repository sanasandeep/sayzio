import { Link } from "wouter";
import { PageLayout } from "@/components/layout/page-layout";
import { MarketingHero, SectionHeading, CTABand } from "@/components/marketing/marketing";
import { SIGNUP_URL, PRICING_URL } from "@/config";
import { useCases } from "@/content/use-cases";
import { ArrowRight } from "lucide-react";
import { motion } from "framer-motion";

export default function Services() {
  return (
    <PageLayout
      title="Use cases — Sayzio for everyone"
      description="Whoever you are, Sayzio is the all-in-one link, monetization and growth stack. See how creators, agencies, coaches, musicians and small businesses use it."
    >
      <MarketingHero
        eyebrow="Use cases"
        title="Built for creators, brands &"
        highlight="networking pros."
        subtitle="Pick the one that fits you — the same all-in-one toolkit powers all of them."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "See pricing", href: PRICING_URL }}
      />

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="One platform" title="Find the playbook that fits you." />
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
            {useCases.map((u, i) => (
              <motion.div
                key={u.slug}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: (i % 3) * 0.08 }}
              >
                <Link
                  href={`/for/${u.slug}`}
                  className="glass-card rounded-3xl p-7 block hover:border-primary/40 transition-colors h-full"
                >
                  <div className="text-xs font-semibold uppercase tracking-wider text-primary mb-3">
                    {u.eyebrow}
                  </div>
                  <h3 className="text-xl font-bold mb-2">{u.tagline}</h3>
                  <p className="text-sm text-muted-foreground mb-4">{u.description}</p>
                  <span className="text-sm font-semibold text-primary inline-flex items-center gap-1">
                    Explore <ArrowRight className="w-4 h-4" />
                  </span>
                </Link>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      <CTABand
        title="Whoever you are, your link is waiting."
        subtitle="Build the page. Share the link. Watch them show up. Free forever, no credit card."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "Compare all plans", href: PRICING_URL }}
      />
    </PageLayout>
  );
}
