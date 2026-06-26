import { PageLayout } from "@/components/layout/page-layout";
import { Button } from "@/components/ui/button";
import { CTABand, Eyebrow } from "@/components/marketing/marketing";
import { LinkTypesShowcase } from "@/components/marketing/link-types-showcase";
import { AiFlipTiles } from "@/components/marketing/ai-flip-tiles";
import { OrbitalUniverse } from "@/components/marketing/orbital-universe";
import { BrandSayzio } from "@/components/marketing/brand-sayzio";
import { motion, useReducedMotion } from "framer-motion";
import { SIGNUP_URL } from "@/config";
import { Link } from "wouter";
import {
  ArrowRight,
  Sparkles,
  Gauge,
  Wand2,
  Bot,
  PhoneCall,
  Workflow,
  Code2,
  Check,
  Link2,
  Globe,
  type LucideIcon,
} from "lucide-react";
import zioMascot from "@assets/icon_1782443779300.png";

interface AiCapability {
  icon: LucideIcon;
  title: string;
  desc: string;
  accent: string;
}

/**
 * Headline AI capabilities surfaced near the top of the homepage. Mirrors the
 * AI Suite mega-menu (Chat Widgets / AI Agents / AI Widget / AI Voice) plus the two flagship
 * in-product AI features (Performance Coach, biolink builder) so the page leads
 * with the AI story. Accents are decorative icon tints only — primary UI stays
 * on the blue brand palette.
 */
const aiCapabilities: AiCapability[] = [
  {
    icon: Gauge,
    title: "AI Performance Coach",
    desc: "Reads your analytics and tells you exactly what to fix next to grow — in plain English.",
    accent: "#3d6bff",
  },
  {
    icon: Wand2,
    title: "AI Page Builder",
    desc: "Describe your page in a sentence and watch AI assemble a full Link in Bio, ready to publish.",
    accent: "#6e61ff",
  },
  {
    icon: Bot,
    title: "Chat Widgets",
    desc: "A 24/7 assistant on your page, trained on your content, answering visitors in your voice.",
    accent: "#1bd4d9",
  },
  {
    icon: PhoneCall,
    title: "AI Voice Assistant",
    desc: "Picks up every call to your number in your voice, qualifies callers and books real meetings.",
    accent: "#ff8a3c",
  },
  {
    icon: Workflow,
    title: "AI Agents",
    desc: "Runs multi-step playbooks across your inbox, calendar and contacts — a teammate, not a bot.",
    accent: "#e94e8c",
  },
  {
    icon: Code2,
    title: "AI Widget",
    desc: "Embed your on-brand assistant on any website with a single snippet — it captures leads in context.",
    accent: "#3d6bff",
  },
];

export default function Home() {
  const prefersReducedMotion = useReducedMotion();

  const float = (offset: number, duration: number, delay = 0) =>
    prefersReducedMotion
      ? {}
      : {
          animate: { y: [0, offset, 0] },
          transition: {
            repeat: Infinity,
            duration,
            ease: "easeInOut" as const,
            delay,
          },
        };

  const reveal = (delay: number) =>
    prefersReducedMotion
      ? {}
      : {
          initial: { opacity: 0, y: 20 },
          whileInView: { opacity: 1, y: 0 },
          viewport: { once: true, margin: "-80px" },
          transition: { duration: 0.5, delay },
        };

  return (
    <PageLayout
      title="The AI-first marketing toolkit"
      description="Sayzio is the AI-first marketing toolkit that redefines how creators and businesses market themselves — AI pages, an AI Performance Coach, an on-brand AI assistant and more. Free forever, no card required."
    >
      {/* ─── Hero — AI-first ──────────────────────────────────────── */}
      <section className="relative pt-32 pb-20 lg:pt-44 lg:pb-28 overflow-hidden">
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
                The AI-first marketing toolkit
              </span>

              <h1 className="text-5xl lg:text-7xl font-bold tracking-tight text-foreground mb-6 leading-[1.05]">
                One AI runs your whole{" "}
                <span className="grad-text">universe</span>
              </h1>

              <p className="text-lg lg:text-xl text-muted-foreground mb-10 max-w-xl">
                Meet Zio, the AI at the center of Sayzio. Every tool orbits
                around it — building your pages, coaching your growth, answering
                visitors and even picking up your calls. One link, an AI suite
                that markets you 24/7 — free forever, no card required.
              </p>

              <div className="flex flex-col sm:flex-row gap-4">
                <Button asChild size="lg" className="rounded-full h-14 px-8 text-base">
                  <a href={SIGNUP_URL}>
                    Start free with AI <ArrowRight className="ml-2 w-5 h-5" />
                  </a>
                </Button>
                <Button
                  asChild
                  variant="outline"
                  size="lg"
                  className="rounded-full h-14 px-8 text-base bg-transparent border-primary/20 hover:bg-primary/5"
                >
                  <a href="#ai-suite">Meet the AI suite</a>
                </Button>
              </div>

              <div className="mt-12 flex flex-wrap gap-x-8 gap-y-4 text-sm font-medium text-muted-foreground">
                <div className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full bg-green-500" />
                  120,000+ creators served
                </div>
                <div>AI built-in, not bolted on</div>
                <div>Free forever</div>
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

      {/* ─── AI Suite — front and centre ──────────────────────────── */}
      <section
        id="ai-suite"
        className="relative py-24 bg-card/50 border-y overflow-hidden scroll-mt-24"
      >
        <div className="mesh-bg" aria-hidden />
        <div className="container mx-auto px-6 relative z-10">
          <motion.div className="text-center mb-16 max-w-3xl mx-auto" {...reveal(0)}>
            <Eyebrow>An AI suite, built in</Eyebrow>
            <h2 className="text-3xl lg:text-5xl font-bold tracking-tight mb-4">
              AI that <span className="grad-text">markets you</span>, around the clock
            </h2>
            <p className="text-muted-foreground text-lg">
              Sayzio isn't a link tool with AI sprinkled on top. AI runs through
              everything — from building your page to coaching your growth and
              talking to your audience while you sleep.
            </p>
          </motion.div>

          <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {aiCapabilities.map((cap, index) => (
              <motion.div
                key={cap.title}
                className="glass-card rounded-3xl p-7 flex flex-col"
                {...reveal((index % 3) * 0.08)}
              >
                <span
                  className="w-12 h-12 rounded-2xl flex items-center justify-center mb-5"
                  style={{ color: cap.accent, backgroundColor: `${cap.accent}1f` }}
                >
                  <cap.icon className="w-6 h-6" />
                </span>
                <h3 className="text-lg font-semibold mb-2">{cap.title}</h3>
                <p className="text-sm text-muted-foreground leading-relaxed">
                  {cap.desc}
                </p>
              </motion.div>
            ))}
          </div>

          <motion.div className="mt-12 flex justify-center" {...reveal(0.1)}>
            <Button asChild size="lg" className="rounded-full h-14 px-8 text-base">
              <a href={SIGNUP_URL}>
                Put the AI suite to work <ArrowRight className="ml-2 w-5 h-5" />
              </a>
            </Button>
          </motion.div>
        </div>
      </section>

      {/* ─── AI feature flip tiles — full toolkit ─────────────────── */}
      <AiFlipTiles />

      {/* ─── 1IN.ME is Sayzio — brand-relationship story ──────────── */}
      <BrandSayzio />

      {/* ─── Sayzio is the new 1INME ──────────────────────────────── */}
      <section className="relative py-24 overflow-hidden">
        <div className="container mx-auto px-6 relative z-10">
          <div className="glass-card rounded-[2rem] p-8 lg:p-14 overflow-hidden relative">
            <div
              aria-hidden
              className="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-primary/15 blur-3xl"
            />
            <div className="grid lg:grid-cols-[1fr_auto] gap-10 lg:gap-14 items-center relative">
              <motion.div {...reveal(0)}>
                <Eyebrow>A new name, the same home</Eyebrow>
                <h2 className="text-3xl lg:text-4xl font-bold tracking-tight mb-4">
                  Sayzio is the new <span className="grad-text">1INME</span>
                </h2>
                <p className="text-muted-foreground text-lg leading-relaxed mb-6">
                  1INME grew up. As we rebuilt the platform around AI — pages that
                  build themselves, a coach that grows you, an assistant that never
                  sleeps — the name needed to say what it does: let your audience
                  hear you. So 1INME became <strong className="text-foreground">Sayzio</strong>.
                </p>
                <ul className="space-y-3 mb-8">
                  {[
                    "Your existing 1INME links keep working — nothing to migrate, nothing to re-share.",
                    "The 1in.me domain stays live as part of the Sayzio global domain family.",
                    "Same account, same data, same free-forever plan — just smarter, with AI built in.",
                  ].map((item) => (
                    <li key={item} className="flex items-start gap-3">
                      <span className="shrink-0 mt-0.5 w-6 h-6 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                        <Check className="w-4 h-4" />
                      </span>
                      <span className="text-sm font-medium text-foreground">{item}</span>
                    </li>
                  ))}
                </ul>
                <div className="flex flex-wrap gap-3">
                  <span className="inline-flex items-center gap-2 rounded-full border border-border bg-secondary/60 px-4 py-2 text-sm font-medium text-foreground">
                    <Link2 className="w-4 h-4 text-primary" />
                    1in.me links still work
                  </span>
                  <span className="inline-flex items-center gap-2 rounded-full border border-border bg-secondary/60 px-4 py-2 text-sm font-medium text-foreground">
                    <Globe className="w-4 h-4 text-primary" />
                    Part of the global domain family
                  </span>
                </div>
              </motion.div>

              <motion.div className="relative mx-auto" {...reveal(0.1)}>
                <div className="relative w-40 lg:w-52">
                  <div
                    aria-hidden
                    className="absolute inset-0 -z-10 rounded-full bg-primary/20 blur-2xl"
                  />
                  <motion.img
                    src={zioMascot}
                    alt="Zio waving hello"
                    className="w-full h-auto drop-shadow-xl select-none"
                    draggable={false}
                    {...float(-12, 5.5)}
                  />
                  <div className="mt-4 text-center">
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                      <Sparkles className="w-3.5 h-3.5" />
                      Hi, I'm Zio
                    </span>
                  </div>
                </div>
              </motion.div>
            </div>
          </div>
        </div>
      </section>

      {/* ─── What you can create — supporting catalog ─────────────── */}
      <LinkTypesShowcase />

      <CTABand
        title="Let AI do your marketing's heavy lifting."
        subtitle="Build the page, coach the growth, answer the visitors — all from one link, all powered by Zio."
        primary={{ label: "Start free with AI", href: SIGNUP_URL }}
        secondary={{ label: "See features", href: "/features" }}
      />
    </PageLayout>
  );
}
