import { PageLayout } from "@/components/layout/page-layout";
import { MarketingHero, SectionHeading, CTABand } from "@/components/marketing/marketing";
import { SIGNUP_URL, PRICING_URL } from "@/config";
import { motion } from "framer-motion";

const steps = [
  { time: "0:15", title: "Sign up free", body: "Email or one-tap Google. Pick your handle and you're in." },
  { time: "0:45", title: "Build your page", body: "Drag-and-drop blocks for socials, music, shop, video, forms." },
  { time: "1:30", title: "Share it everywhere", body: "One link, branded short links and a dynamic QR for offline." },
  { time: "2:00", title: "Watch it grow", body: "Live analytics + an AI Coach that turns numbers into actions." },
];

export default function HowItWorks() {
  return (
    <PageLayout
      title="How it works"
      description="Four tiny steps from 'I have an idea' to 'share my link'. No card, no setup call, no fuss."
    >
      <MarketingHero
        eyebrow="How it works"
        title="Live in under"
        highlight="2 minutes."
        subtitle="Four tiny steps from 'I have an idea' to 'share my link'. No card, no setup call, no fuss."
        primary={{ label: "Start free — no card", href: SIGNUP_URL }}
        secondary={{ label: "See pricing", href: PRICING_URL }}
      />

      <section className="py-12">
        <div className="container mx-auto px-6">
          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-6xl mx-auto">
            {steps.map((step, i) => (
              <motion.div
                key={step.title}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: i * 0.08 }}
                className="glass-card rounded-3xl p-7"
              >
                <div className="flex items-center justify-between mb-5">
                  <div className="w-9 h-9 rounded-full bg-primary text-primary-foreground flex items-center justify-center font-bold">
                    {i + 1}
                  </div>
                  <span className="text-sm font-mono text-primary">{step.time}</span>
                </div>
                <h3 className="font-semibold mb-2">{step.title}</h3>
                <p className="text-sm text-muted-foreground">{step.body}</p>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      <CTABand
        title="Start free — no card needed."
        subtitle="Free Forever plan · Upgrade only when you outgrow it."
        primary={{ label: "Start free — no card", href: SIGNUP_URL }}
        secondary={{ label: "Compare all plans", href: PRICING_URL }}
      />
    </PageLayout>
  );
}
