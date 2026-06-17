import { PageLayout } from "@/components/layout/page-layout";
import {
  MarketingHero,
  SectionHeading,
  FeatureGrid,
  CTABand,
  StatRow,
  FaqAccordion,
} from "@/components/marketing/marketing";
import { SIGNUP_URL, PRICING_URL } from "@/config";
import { UserPlus, Wand2, Palette, Share2, Check, X } from "lucide-react";
import { motion } from "framer-motion";

const steps = [
  { icon: UserPlus, name: "Tell us about you", description: "Paste a LinkedIn URL or fill in 5 quick fields. We pre-fill everything we can." },
  { icon: Wand2, name: "Let AI polish it", description: "AI rewrites bullet points with metrics, action verbs and ATS keywords for your role." },
  { icon: Palette, name: "Pick a template", description: "20+ recruiter-tested designs. Recolor, reorder, swap fonts — all live preview." },
  { icon: Share2, name: "Share & export", description: "Public link at 1inme.com/you/cv, private link, or pixel-perfect PDF download." },
];

const oldWay = [
  "Wrestle with Word margins for 3 hours",
  "$45 for a \"premium\" template that breaks ATS",
  "Email a static PDF and hope they open it",
  "Rewrite every bullet point yourself",
  "No portfolio link — or one stuck on Behance",
];

const newWay = [
  "Drag, drop, done — live preview, no save button",
  "20+ templates, all free, all ATS-clean",
  "Public link with view analytics — know who looked",
  "AI polishes every bullet with metrics & keywords",
  "Portfolio & résumé live together at 1inme.com/you",
];

const faqs = [
  { question: "Is it really free?", answer: "Yes — the Free Forever plan includes unlimited public portfolios and 3 PDF exports per month. Upgrade only if you need unlimited exports or premium templates." },
  { question: "Will my résumé pass ATS systems?", answer: "Every template is structured so that Greenhouse, Lever, Workday and other ATS parsers read it cleanly. The PDF export uses selectable text and embedded fonts — no scanned images." },
  { question: "Can I import from LinkedIn?", answer: "Paste your LinkedIn URL and we pre-fill experience, education and skills. You can edit everything before publishing." },
  { question: "Who can see my portfolio link?", answer: "You choose: public (indexed and discoverable), unlisted (link-only) or private (only you). Email and phone can be hidden from public view in one tap." },
  { question: "Can I host multiple résumés?", answer: "Yes — create a different version for every role. Each gets its own URL slug like 1inme.com/you/cv-design or 1inme.com/you/cv-pm." },
];

export default function ResumeBuilder() {
  return (
    <PageLayout
      title="Résumé & Portfolio Builder"
      description="Drag-and-drop sections, AI-polished bullet points and 20+ recruiter-tested templates — a public portfolio link and pixel-perfect PDF export."
    >
      <MarketingHero
        eyebrow="Résumé & Portfolio"
        title="Your résumé & portfolio,"
        highlight="built in 5 minutes flat."
        subtitle="Drag-and-drop sections. AI-polished bullet points. 20+ recruiter-tested templates. A public portfolio link at 1inme.com/you/cv — and a pixel-perfect PDF export when you need to email it."
        primary={{ label: "Start free — no card", href: SIGNUP_URL }}
        secondary={{ label: "See pricing", href: PRICING_URL }}
      >
        <StatRow
          stats={[
            { value: "5 min", label: "Average build time" },
            { value: "20+", label: "Recruiter-tested templates" },
            { value: "1-tap", label: "PDF export" },
            { value: "100%", label: "ATS-friendly" },
          ]}
        />
      </MarketingHero>

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading
            eyebrow="How it works"
            title="From blank page to 'hire me'."
            subtitle="Four steps. No design degree, no template fees, no recruiter rejection."
          />
          <FeatureGrid items={steps} />
        </div>
      </section>

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="Why 1INME" title="The old way vs. the 1INME way." />
          <div className="grid md:grid-cols-2 gap-6 max-w-4xl mx-auto">
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              className="glass-card rounded-3xl p-8"
            >
              <div className="text-xs font-bold uppercase tracking-wider text-red-400 mb-4">The old way</div>
              <ul className="space-y-3">
                {oldWay.map((item) => (
                  <li key={item} className="flex items-start gap-2.5 text-sm text-muted-foreground">
                    <X className="w-4 h-4 text-red-400 mt-0.5 shrink-0" />
                    <span>{item}</span>
                  </li>
                ))}
              </ul>
            </motion.div>
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ delay: 0.1 }}
              className="glass-card rounded-3xl p-8 border-primary/30"
            >
              <div className="text-xs font-bold uppercase tracking-wider text-emerald-400 mb-4">The 1INME way</div>
              <ul className="space-y-3">
                {newWay.map((item) => (
                  <li key={item} className="flex items-start gap-2.5 text-sm">
                    <Check className="w-4 h-4 text-emerald-400 mt-0.5 shrink-0" />
                    <span>{item}</span>
                  </li>
                ))}
              </ul>
            </motion.div>
          </div>
        </div>
      </section>

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="FAQs" title="Quick answers." />
          <FaqAccordion items={faqs} />
        </div>
      </section>

      <CTABand
        title="Your next role is one résumé away."
        subtitle="Build it free in 5 minutes. Share it as a link or download a perfect PDF. No card, no setup call, no fuss."
        primary={{ label: "Start building — free", href: SIGNUP_URL }}
        secondary={{ label: "See plans", href: PRICING_URL }}
      />
    </PageLayout>
  );
}
