import { Link } from "wouter";
import { motion } from "framer-motion";
import { Button } from "@/components/ui/button";
import { Check, type LucideIcon } from "lucide-react";

/**
 * A CTA link that automatically renders an external anchor for absolute URLs
 * (e.g. the 1in.me auth/pricing destinations) and a client-side wouter Link
 * for internal marketing routes.
 */
export interface CtaSpec {
  label: string;
  href: string;
  variant?: "default" | "outline" | "secondary" | "ghost";
}

function isExternal(href: string): boolean {
  return /^https?:\/\//.test(href) || href.startsWith("#") || href.startsWith("mailto:");
}

export function Cta({ label, href, variant = "default", className }: CtaSpec & { className?: string }) {
  const cls = className ?? "rounded-full px-7 h-12 text-base";
  if (isExternal(href)) {
    return (
      <Button asChild variant={variant} className={cls}>
        <a href={href}>{label}</a>
      </Button>
    );
  }
  return (
    <Button asChild variant={variant} className={cls}>
      <Link href={href}>{label}</Link>
    </Button>
  );
}

export function Eyebrow({ children }: { children: React.ReactNode }) {
  return (
    <span className="inline-block text-xs font-bold uppercase tracking-[0.2em] text-primary mb-4">
      {children}
    </span>
  );
}

export function MarketingHero({
  eyebrow,
  title,
  highlight,
  subtitle,
  primary,
  secondary,
  children,
}: {
  eyebrow?: string;
  title: React.ReactNode;
  highlight?: string;
  subtitle?: React.ReactNode;
  primary?: CtaSpec;
  secondary?: CtaSpec;
  children?: React.ReactNode;
}) {
  return (
    <section className="relative pt-20 lg:pt-28 pb-16 overflow-hidden">
      <div className="mesh-bg" aria-hidden />
      <div className="grid-bg" aria-hidden />
      <div className="container mx-auto px-6 relative z-10">
        <motion.div
          initial={{ opacity: 0, y: 24 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6 }}
          className="max-w-3xl mx-auto text-center"
        >
          {eyebrow && <Eyebrow>{eyebrow}</Eyebrow>}
          <h1 className="text-4xl lg:text-6xl font-bold tracking-tight mb-6 leading-[1.05]">
            {title}{" "}
            {highlight && <span className="grad-text">{highlight}</span>}
          </h1>
          {subtitle && (
            <p className="text-lg lg:text-xl text-muted-foreground leading-relaxed">{subtitle}</p>
          )}
          {(primary || secondary) && (
            <div className="mt-9 flex flex-wrap items-center justify-center gap-4">
              {primary && <Cta {...primary} />}
              {secondary && <Cta variant="outline" {...secondary} />}
            </div>
          )}
          {children}
        </motion.div>
      </div>
    </section>
  );
}

export function SectionHeading({
  eyebrow,
  title,
  subtitle,
  center = true,
}: {
  eyebrow?: string;
  title: React.ReactNode;
  subtitle?: React.ReactNode;
  center?: boolean;
}) {
  return (
    <div className={`max-w-3xl mb-14 ${center ? "mx-auto text-center" : ""}`}>
      {eyebrow && <Eyebrow>{eyebrow}</Eyebrow>}
      <h2 className="text-3xl lg:text-4xl font-bold tracking-tight mb-4">{title}</h2>
      {subtitle && <p className="text-lg text-muted-foreground leading-relaxed">{subtitle}</p>}
    </div>
  );
}

export interface FeatureItem {
  icon?: LucideIcon;
  name: string;
  description: string;
}

export function FeatureGrid({
  items,
  columns = 3,
}: {
  items: FeatureItem[];
  columns?: 2 | 3;
}) {
  const cols = columns === 2 ? "md:grid-cols-2" : "md:grid-cols-2 lg:grid-cols-3";
  return (
    <div className={`grid ${cols} gap-6 lg:gap-8`}>
      {items.map((item, index) => (
        <motion.div
          key={item.name}
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true, margin: "-80px" }}
          transition={{ duration: 0.45, delay: (index % 3) * 0.08 }}
          className="glass-card p-7 rounded-3xl"
        >
          {item.icon && (
            <div className="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center mb-5">
              <item.icon className="w-6 h-6" />
            </div>
          )}
          <h3 className="text-lg font-semibold mb-2">{item.name}</h3>
          <p className="text-sm text-muted-foreground leading-relaxed">{item.description}</p>
        </motion.div>
      ))}
    </div>
  );
}

export function CheckList({ items }: { items: string[] }) {
  return (
    <ul className="space-y-3">
      {items.map((item) => (
        <li key={item} className="flex items-start gap-3">
          <Check className="w-5 h-5 text-primary shrink-0 mt-0.5" />
          <span className="text-sm font-medium">{item}</span>
        </li>
      ))}
    </ul>
  );
}

export function CTABand({
  title,
  subtitle,
  primary,
  secondary,
}: {
  title: React.ReactNode;
  subtitle?: React.ReactNode;
  primary?: CtaSpec;
  secondary?: CtaSpec;
}) {
  return (
    <section className="py-20 lg:py-28">
      <div className="container mx-auto px-6">
        <div className="glass-card rounded-3xl p-10 lg:p-16 text-center max-w-4xl mx-auto relative overflow-hidden">
          <div className="absolute inset-0 bg-gradient-to-br from-primary/10 via-transparent to-accent-foreground/10 pointer-events-none" />
          <div className="relative">
            <h2 className="text-3xl lg:text-4xl font-bold tracking-tight mb-4">{title}</h2>
            {subtitle && (
              <p className="text-lg text-muted-foreground max-w-2xl mx-auto mb-8">{subtitle}</p>
            )}
            <div className="flex flex-wrap items-center justify-center gap-4">
              {primary && <Cta {...primary} />}
              {secondary && <Cta variant="outline" {...secondary} />}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

export function FaqAccordion({ items }: { items: { question: string; answer: string }[] }) {
  return (
    <div className="max-w-3xl mx-auto divide-y divide-border rounded-3xl glass-card overflow-hidden">
      {items.map((item) => (
        <details key={item.question} className="group">
          <summary className="flex items-center justify-between cursor-pointer list-none px-6 py-5 font-medium hover:text-primary transition-colors">
            {item.question}
            <span className="ml-4 text-primary transition-transform group-open:rotate-45 text-xl leading-none">
              +
            </span>
          </summary>
          <p className="px-6 pb-5 -mt-1 text-sm text-muted-foreground leading-relaxed">
            {item.answer}
          </p>
        </details>
      ))}
    </div>
  );
}

export function StatRow({ stats }: { stats: { value: string; label: string }[] }) {
  return (
    <div className="mt-12 grid grid-cols-2 sm:grid-cols-4 gap-4 max-w-3xl mx-auto">
      {stats.map((s) => (
        <div key={s.label} className="glass-card rounded-2xl px-4 py-5 text-center">
          <div className="text-2xl lg:text-3xl font-bold text-primary">{s.value}</div>
          <div className="text-xs text-muted-foreground mt-1">{s.label}</div>
        </div>
      ))}
    </div>
  );
}
