import { PageLayout } from "@/components/layout/page-layout";
import { motion, useReducedMotion } from "framer-motion";
import { CTABand } from "@/components/marketing/marketing";
import { OrbitalUniverse } from "@/components/marketing/orbital-universe";
import { Button } from "@/components/ui/button";
import { featuresCategories } from "@/content/features";
import { SIGNUP_URL, PRICING_URL } from "@/config";
import { ArrowRight, Sparkles } from "lucide-react";

export default function Features() {
  const prefersReducedMotion = useReducedMotion();

  return (
    <PageLayout
      title="Features"
      description="Every tool you need to create, share, track and grow — Link in Bio pages, short links, QR codes, analytics, AI, forms, inbox, teams and more, in one platform."
    >
      {/* ─── Hero — the orbital Zio universe, tuned for the catalog ─── */}
      <section className="relative pt-28 pb-16 lg:pt-36 lg:pb-20 overflow-hidden">
        <div className="mesh-bg" aria-hidden />
        <div className="grid-bg" aria-hidden />
        <div className="container mx-auto px-6 relative z-10">
          <div className="grid lg:grid-cols-2 gap-12 lg:gap-8 items-center">
            <motion.div
              initial={prefersReducedMotion ? false : { opacity: 0, y: 20 }}
              animate={prefersReducedMotion ? undefined : { opacity: 1, y: 0 }}
              transition={{ duration: 0.6 }}
              className="max-w-2xl"
            >
              <span className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-1.5 text-sm font-semibold text-primary mb-6">
                <Sparkles className="w-4 h-4" />
                The complete toolkit
              </span>

              <h1 className="text-4xl lg:text-6xl font-bold tracking-tight text-foreground mb-6 leading-[1.05]">
                Every tool, in orbit around{" "}
                <span className="grad-text">one AI.</span>
              </h1>

              <p className="text-lg lg:text-xl text-muted-foreground mb-10 max-w-xl">
                Pages, short links, QR codes, analytics, forms, an inbox and a
                full AI suite — all in a single dashboard with Zio at the center.
                Explore every category below, or just describe what you want and
                let the AI build it.
              </p>

              <div className="flex flex-col sm:flex-row gap-4">
                <Button asChild size="lg" className="rounded-full h-14 px-8 text-base">
                  <a href={SIGNUP_URL}>
                    Get started free <ArrowRight className="ml-2 w-5 h-5" />
                  </a>
                </Button>
                <Button
                  asChild
                  variant="outline"
                  size="lg"
                  className="rounded-full h-14 px-8 text-base bg-transparent border-primary/20 hover:bg-primary/5"
                >
                  <a href={PRICING_URL}>See pricing</a>
                </Button>
              </div>
            </motion.div>

            {/* Zio universe — orbital feature planets */}
            <motion.div
              initial={prefersReducedMotion ? false : { opacity: 0, scale: 0.92 }}
              animate={prefersReducedMotion ? undefined : { opacity: 1, scale: 1 }}
              transition={{ duration: 0.8, delay: 0.2 }}
              className="relative flex justify-center lg:justify-end"
            >
              <OrbitalUniverse />
            </motion.div>
          </div>
        </div>
      </section>

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
