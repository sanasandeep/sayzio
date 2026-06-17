import { PageLayout } from "@/components/layout/page-layout";
import { motion } from "framer-motion";
import { MarketingHero, CTABand } from "@/components/marketing/marketing";
import { featuresCategories } from "@/content/features";
import { SIGNUP_URL, PRICING_URL } from "@/config";

export default function Features() {
  return (
    <PageLayout
      title="Features"
      description="Every tool you need to create, share, track and grow — biolinks, short links, QR codes, analytics, AI, forms, inbox, teams and more, in one platform."
    >
      <MarketingHero
        eyebrow="Features"
        title="Everything you need,"
        highlight="all in one link."
        subtitle="From a simple short link to a full AI chatbot — 1INME packs the entire toolkit into a single dashboard. Explore the categories below."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "See pricing", href: PRICING_URL }}
      />

      <section className="pb-12">
        <div className="container mx-auto px-6">
          <nav className="flex flex-wrap justify-center gap-2 max-w-4xl mx-auto">
            {featuresCategories.map((cat) => (
              <a
                key={cat.id}
                href={`#${cat.id}`}
                className="text-xs font-medium px-3 py-1.5 rounded-full glass-card hover:text-primary transition-colors"
              >
                {cat.name.split(" — ")[0]}
              </a>
            ))}
          </nav>
        </div>
      </section>

      {featuresCategories.map((cat, catIndex) => (
        <section
          key={cat.id}
          id={cat.id}
          className={`py-16 scroll-mt-24 ${catIndex % 2 === 1 ? "bg-muted/30" : ""}`}
        >
          <div className="container mx-auto px-6">
            <div className="max-w-3xl mb-12">
              <h2 className="text-2xl lg:text-3xl font-bold tracking-tight mb-4">
                {cat.name}
              </h2>
              <p className="text-lg text-muted-foreground leading-relaxed">{cat.intro}</p>
            </div>
            <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
              {cat.items.map((item, index) => (
                <motion.div
                  key={item.title}
                  initial={{ opacity: 0, y: 20 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true, margin: "-80px" }}
                  transition={{ duration: 0.4, delay: (index % 3) * 0.07 }}
                  className="glass-card p-6 rounded-3xl"
                >
                  <h3 className="text-base font-semibold mb-2">{item.title}</h3>
                  <p className="text-sm text-muted-foreground leading-relaxed">
                    {item.description}
                  </p>
                </motion.div>
              ))}
            </div>
          </div>
        </section>
      ))}

      <CTABand
        title="Ready to bring it all together?"
        subtitle="Start free, no credit card required — and upgrade only when you need more."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "Compare all plans", href: PRICING_URL }}
      />
    </PageLayout>
  );
}
