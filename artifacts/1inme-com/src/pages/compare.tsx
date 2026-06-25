import { Link } from "wouter";
import { PageLayout } from "@/components/layout/page-layout";
import {
  MarketingHero,
  SectionHeading,
  CTABand,
} from "@/components/marketing/marketing";
import { SIGNUP_URL, PRICING_URL } from "@/config";
import { competitors, compareGroups, featureSupport, totalFeatures } from "@/content/compare";
import { motion } from "framer-motion";
import { Check, X, ArrowRight } from "lucide-react";

const columnKeys = ["ours", ...competitors.map((c) => c.slug)];
const columnLabels = ["Sayzio", ...competitors.map((c) => c.name)];

export default function Compare() {
  return (
    <PageLayout
      title="Compare Sayzio"
      description="See how Sayzio stacks up against Linktree, Bitly, Beacons, Carrd, Taplink and Stan — the whole growth stack behind one link."
    >
      <MarketingHero
        eyebrow="Compare"
        title="The whole growth stack, in one"
        highlight="link."
        subtitle="A Link in Bio is the start, not the finish. See how Sayzio compares to the tools you already know — across every feature that matters."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "See pricing", href: PRICING_URL }}
      />

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading
            eyebrow="Head to head"
            title="Pick a rival to see the full breakdown."
          />
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 max-w-5xl mx-auto">
            {competitors.map((c, i) => (
              <motion.div
                key={c.slug}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: (i % 3) * 0.08 }}
              >
                <Link
                  href={`/compare/${c.slug}`}
                  className="glass-card rounded-3xl p-7 block hover:border-primary/40 transition-colors h-full"
                >
                  <div className="flex items-center justify-between mb-3">
                    <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                      {c.tagline}
                    </span>
                    <span className="text-xs font-bold text-primary bg-primary/10 rounded-full px-3 py-1">
                      {c.badge}
                    </span>
                  </div>
                  <h3 className="text-xl font-bold mb-2">Sayzio vs {c.name}</h3>
                  <p className="text-sm text-muted-foreground mb-4">{c.headline}</p>
                  <span className="text-sm font-semibold text-primary inline-flex items-center gap-1">
                    See comparison <ArrowRight className="w-4 h-4" />
                  </span>
                </Link>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading
            eyebrow="Feature matrix"
            title={`${totalFeatures} features, fully mapped.`}
            subtitle="Everything Sayzio includes out of the box, compared with the rest."
          />
          <div className="overflow-x-auto max-w-6xl mx-auto glass-card rounded-3xl p-2">
            <table className="w-full text-sm border-collapse">
              <thead>
                <tr>
                  <th className="text-left p-4 font-semibold sticky left-0 bg-card/80 backdrop-blur-sm">
                    Feature
                  </th>
                  {columnLabels.map((label, i) => (
                    <th
                      key={label}
                      className={`p-4 text-center font-semibold whitespace-nowrap ${
                        i === 0
                          ? "text-primary-foreground"
                          : "text-muted-foreground"
                      }`}
                    >
                      {i === 0 ? (
                        <span className="inline-flex items-center rounded-full bg-primary px-3 py-1 text-primary-foreground shadow-lg shadow-primary/40">
                          {label}
                        </span>
                      ) : (
                        label
                      )}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {compareGroups.map((group) => (
                  <>
                    <tr key={group.category}>
                      <td
                        colSpan={columnKeys.length + 1}
                        className="px-4 pt-6 pb-2 text-xs font-bold uppercase tracking-wider text-primary"
                      >
                        {group.category}
                      </td>
                    </tr>
                    {group.features.map((feature) => (
                      <tr key={feature} className="border-t border-border/50">
                        <td className="p-4 sticky left-0 bg-card/40 backdrop-blur-sm font-medium">
                          {feature}
                        </td>
                        {columnKeys.map((key, ci) => {
                          const supported = featureSupport[feature]?.[key];
                          const isOurs = ci === 0;
                          return (
                            <td
                              key={key}
                              className={`p-4 text-center ${isOurs ? "bg-primary/[0.06]" : ""}`}
                            >
                              {supported ? (
                                <span
                                  className={`mx-auto inline-flex h-7 w-7 items-center justify-center rounded-full ${
                                    isOurs
                                      ? "bg-primary text-primary-foreground shadow-md shadow-primary/30"
                                      : "bg-emerald-500/15 text-emerald-500 dark:text-emerald-400"
                                  }`}
                                >
                                  <Check className="w-4 h-4" strokeWidth={3} />
                                </span>
                              ) : (
                                <span className="mx-auto inline-flex h-7 w-7 items-center justify-center rounded-full bg-muted text-muted-foreground/50">
                                  <X className="w-3.5 h-3.5" />
                                </span>
                              )}
                            </td>
                          );
                        })}
                      </tr>
                    ))}
                  </>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <CTABand
        title="Make the switch in minutes."
        subtitle="Free forever, no credit card. Bring your links across and your audience never notices the move."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "Compare all plans", href: PRICING_URL }}
      />
    </PageLayout>
  );
}
