import { motion, useReducedMotion } from "framer-motion";
import { linkTypeCategories, LINK_TYPE_COUNT } from "@/content/link-types";

/**
 * Responsive recreation of the "12 ways to share who you are" poster
 * (`exports/1inme-link-types-poster-landscape.png`). Renders the grouped
 * link-type catalog mirrored from the Laravel `LinkTypeCategories` source of
 * truth, so it stays in sync with the product copy.
 *
 * Four numbered category columns on desktop, collapsing to two / one on
 * smaller screens. All entrance motion respects `prefers-reduced-motion`.
 */
export function LinkTypesShowcase() {
  const prefersReducedMotion = useReducedMotion();

  const reveal = (delay: number) =>
    prefersReducedMotion
      ? {}
      : {
          initial: { opacity: 0, y: 20 },
          whileInView: { opacity: 1, y: 0 },
          viewport: { once: true, margin: "-80px" },
          transition: { duration: 0.45, delay },
        };

  return (
    <section className="relative py-24 bg-card/50 border-y overflow-hidden">
      <div className="mesh-bg" aria-hidden />
      <div className="container mx-auto px-6 relative z-10">
        <motion.div className="text-center mb-16" {...reveal(0)}>
          <span className="inline-block text-xs font-bold uppercase tracking-[0.2em] text-primary mb-4">
            Everything you can create with 1INME
          </span>
          <h2 className="text-3xl lg:text-5xl font-bold tracking-tight mb-4">
            <span className="grad-text">{LINK_TYPE_COUNT} ways to share</span> who you are
          </h2>
          <p className="text-muted-foreground text-lg max-w-2xl mx-auto">
            From a humble short link to AI-powered pages — all from one profile.
          </p>
        </motion.div>

        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {linkTypeCategories.map((category, catIndex) => (
            <motion.div
              key={category.label}
              className="glass-card rounded-3xl p-6 flex flex-col"
              {...reveal(catIndex * 0.08)}
            >
              <div className="flex items-baseline gap-3 mb-2">
                <span className="text-2xl font-bold grad-text tabular-nums">
                  {String(catIndex + 1).padStart(2, "0")}
                </span>
                <h3 className="text-lg font-semibold leading-tight">{category.label}</h3>
              </div>
              <p className="text-sm text-muted-foreground leading-relaxed mb-6">
                {category.desc}
              </p>

              <ul className="space-y-5">
                {category.types.map((type) => (
                  <li key={type.label} className="flex items-start gap-3">
                    <span className="shrink-0 w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                      <type.icon className="w-5 h-5" />
                    </span>
                    <div>
                      <p className="font-semibold text-sm leading-snug">{type.label}</p>
                      <p className="text-xs text-muted-foreground leading-relaxed mt-0.5">
                        {type.desc}
                      </p>
                    </div>
                  </li>
                ))}
              </ul>
            </motion.div>
          ))}
        </div>

        <p className="text-center text-sm text-muted-foreground mt-12">
          {LINK_TYPE_COUNT} link types · 1 profile
        </p>
      </div>
    </section>
  );
}
