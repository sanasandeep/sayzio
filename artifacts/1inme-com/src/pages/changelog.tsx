import { PageLayout } from "@/components/layout/page-layout";
import { Button } from "@/components/ui/button";
import { SIGNUP_URL } from "@/config";
import { motion } from "framer-motion";
import { Sparkles, Wrench, Zap } from "lucide-react";

type ChangeType = "new" | "improved" | "fixed";

interface ChangelogEntry {
  version: string;
  date: string;
  title: string;
  changes: { type: ChangeType; text: string }[];
}

const typeStyles: Record<
  ChangeType,
  { label: string; icon: typeof Sparkles; className: string }
> = {
  new: {
    label: "New",
    icon: Sparkles,
    className: "bg-primary/10 text-primary",
  },
  improved: {
    label: "Improved",
    icon: Zap,
    className: "bg-blue-500/10 text-blue-500",
  },
  fixed: {
    label: "Fixed",
    icon: Wrench,
    className: "bg-emerald-500/10 text-emerald-500",
  },
};

const entries: ChangelogEntry[] = [
  {
    version: "2.7",
    date: "June 2026",
    title: "Smarter coaching and zoomable trends",
    changes: [
      {
        type: "new",
        text: "Plain-English explanations for every Performance Coach threshold, right on hover.",
      },
      {
        type: "improved",
        text: "The score trend chart is now bigger and zoomable so you can inspect any window.",
      },
      {
        type: "fixed",
        text: "Jumping from an insight now flash-highlights the exact field that needs attention.",
      },
    ],
  },
  {
    version: "2.6",
    date: "May 2026",
    title: "Scheduling and publishing upgrades",
    changes: [
      {
        type: "new",
        text: "Schedule posts to auto-publish at the right time — no manual nudging required.",
      },
      {
        type: "improved",
        text: "Edit posts after publishing without recreating them from scratch.",
      },
      {
        type: "fixed",
        text: "Reschedule or cancel a queued post cleanly before it goes live.",
      },
    ],
  },
  {
    version: "2.5",
    date: "April 2026",
    title: "QR Studio Pro",
    changes: [
      {
        type: "new",
        text: "16 content types, 30+ design templates, and per-corner eye styling in QR Studio.",
      },
      {
        type: "new",
        text: "Live scannability checker grades contrast, logo coverage, and quiet zone before you print.",
      },
      {
        type: "improved",
        text: "Print-ready PDF export with configurable size, DPI, and bleed.",
      },
    ],
  },
  {
    version: "2.4",
    date: "March 2026",
    title: "Analytics that point to fixes",
    changes: [
      {
        type: "new",
        text: "The Performance Coach surfaces ranked, concrete fixes instead of raw numbers.",
      },
      {
        type: "improved",
        text: "Real-time visitor map with country, city, device, and referrer breakdowns.",
      },
      {
        type: "fixed",
        text: "Geographic heatmap now persists coordinates reliably across sessions.",
      },
    ],
  },
];

export default function Changelog() {
  return (
    <PageLayout
      title="Changelog"
      description="Everything new in 1INME — features, improvements, and fixes shipped week after week."
    >
      <section className="py-20 lg:py-32">
        <div className="container mx-auto px-6">
          <div className="max-w-3xl mx-auto text-center mb-16">
            <p className="text-sm font-semibold uppercase tracking-widest text-primary mb-4">
              Changelog
            </p>
            <h1 className="text-4xl lg:text-6xl font-bold tracking-tight mb-6">
              What's{" "}
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-primary to-accent-foreground">
                new.
              </span>
            </h1>
            <p className="text-xl text-muted-foreground">
              New things every week — never on a Friday at 5pm.
            </p>
          </div>

          <div className="max-w-3xl mx-auto space-y-8">
            {entries.map((entry, index) => (
              <motion.div
                key={entry.version}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: "-80px" }}
                transition={{ duration: 0.5, delay: index * 0.05 }}
                className="glass-card p-8 rounded-3xl"
              >
                <div className="flex flex-wrap items-center gap-3 mb-6">
                  <span className="text-lg font-bold text-primary">
                    v{entry.version}
                  </span>
                  <span className="text-sm text-muted-foreground">
                    {entry.date}
                  </span>
                </div>
                <h2 className="text-2xl font-semibold mb-6">{entry.title}</h2>
                <ul className="space-y-4">
                  {entry.changes.map((change, changeIndex) => {
                    const style = typeStyles[change.type];
                    return (
                      <li key={changeIndex} className="flex items-start gap-4">
                        <span
                          className={`inline-flex items-center gap-1.5 shrink-0 px-3 py-1 rounded-full text-xs font-semibold ${style.className}`}
                        >
                          <style.icon className="w-3.5 h-3.5" />
                          {style.label}
                        </span>
                        <span className="text-muted-foreground leading-relaxed pt-0.5">
                          {change.text}
                        </span>
                      </li>
                    );
                  })}
                </ul>
              </motion.div>
            ))}
          </div>

          <div className="max-w-3xl mx-auto text-center mt-16">
            <p className="text-muted-foreground mb-6">
              Want these updates as you grow your audience?
            </p>
            <Button asChild size="lg" className="rounded-full h-14 px-8 text-base">
              <a href={SIGNUP_URL}>Get started for free</a>
            </Button>
          </div>
        </div>
      </section>
    </PageLayout>
  );
}
