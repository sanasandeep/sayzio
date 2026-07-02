import { PageLayout } from "@/components/layout/page-layout";
import { Button } from "@/components/ui/button";
import { CTABand, Eyebrow } from "@/components/marketing/marketing";
import { LinkTypesShowcase } from "@/components/marketing/link-types-showcase";
import { AiFlipTiles } from "@/components/marketing/ai-flip-tiles";
import { AiBuilderShowcase } from "@/components/marketing/ai-builder-showcase";
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
  MessageCircle,
  Mic,
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
                One Platform. Endless Conversations.
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

      {/* ─── AI builder showcase — describe it, AI builds it ──────── */}
      <AiBuilderShowcase />

      {/* ─── WhatsApp Agent — build links from chat ───────────────── */}
      <section
        id="whatsapp-agent"
        className="relative py-24 overflow-hidden scroll-mt-24"
      >
        <div
          aria-hidden
          className="pointer-events-none absolute -left-24 top-0 h-72 w-72 rounded-full blur-3xl"
          style={{ backgroundColor: "#25d36622" }}
        />
        <div
          aria-hidden
          className="pointer-events-none absolute -right-24 bottom-0 h-72 w-72 rounded-full blur-3xl"
          style={{ backgroundColor: "#1da85118" }}
        />
        <div className="container mx-auto px-6 relative z-10">
          <div className="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            <motion.div {...reveal(0)}>
              <span
                className="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] mb-4"
                style={{ color: "#1da851" }}
              >
                <MessageCircle className="w-4 h-4" /> WhatsApp Agent
              </span>
              <h2 className="text-3xl lg:text-5xl font-bold tracking-tight mb-4">
                Build links by{" "}
                <span style={{ color: "#1da851" }}>chatting on WhatsApp</span>
              </h2>
              <p className="text-muted-foreground text-lg leading-relaxed mb-7 max-w-xl">
                Message Ask Zio like a teammate. It creates and edits
                short links, QR codes, contact cards, calendar events and file
                links — right inside the chat. Send a voice note and it
                transcribes it; send a photo and it understands it.
              </p>
              <ul className="space-y-3 mb-9 max-w-xl">
                {[
                  "Create & edit links, QR codes, vCards, events and file links",
                  "Voice notes transcribed automatically (Whisper)",
                  "Drop in a photo and the agent reads it",
                  "No app to open — it all happens in WhatsApp",
                ].map((line) => (
                  <li key={line} className="flex items-start gap-3">
                    <span
                      className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-white"
                      style={{ backgroundColor: "#1da851" }}
                    >
                      <Check className="w-3.5 h-3.5" />
                    </span>
                    <span className="text-foreground/80">{line}</span>
                  </li>
                ))}
              </ul>
              <div className="flex flex-wrap items-center gap-4">
                <Button
                  asChild
                  size="lg"
                  className="rounded-full h-14 px-7 text-base text-white hover:opacity-90"
                  style={{ backgroundColor: "#1da851" }}
                >
                  <a href={SIGNUP_URL}>
                    Get started free <ArrowRight className="ml-2 w-5 h-5" />
                  </a>
                </Button>
                <Button asChild variant="outline" size="lg" className="rounded-full h-14 px-7 text-base">
                  <Link href="/ai/whatsapp-agent">See how it works</Link>
                </Button>
              </div>
              <p className="text-xs text-muted-foreground mt-5 max-w-xl">
                Available on paid plans. Each turn is metered and paid from your
                coin wallet, with an automatic refund if a turn fails. Requires a
                verified phone number.
              </p>
            </motion.div>

            <motion.div className="flex justify-center" {...reveal(0.1)}>
              <div
                className="w-full max-w-sm rounded-[1.75rem] overflow-hidden border"
                style={{ backgroundColor: "#0b141a", boxShadow: "0 40px 90px -40px rgba(0,0,0,0.55)" }}
              >
                <div
                  className="flex items-center gap-3 px-4 py-3 border-b border-white/5"
                  style={{ background: "linear-gradient(135deg,#1f2c33,#111b21)" }}
                >
                  <span
                    className="flex h-9 w-9 items-center justify-center rounded-full text-white"
                    style={{ background: "linear-gradient(135deg,#25d366,#1da851)" }}
                  >
                    <MessageCircle className="w-5 h-5" />
                  </span>
                  <span className="flex flex-col leading-tight">
                    <span className="font-bold text-[#e9edef]">Ask Zio</span>
                    <span className="text-[0.7rem] text-[#8fa3ad]">online</span>
                  </span>
                </div>
                <div
                  className="flex flex-col gap-2.5 p-4"
                  style={{ minHeight: "320px" }}
                >
                  <div className="self-end max-w-[82%] rounded-2xl rounded-tr-sm px-3 py-2 text-sm text-[#e9edef]" style={{ backgroundColor: "#005c4b" }}>
                    Make a short link for my new pricing page sayzio.com/pricing
                  </div>
                  <div className="self-start max-w-[82%] rounded-2xl rounded-tl-sm px-3 py-2 text-sm text-[#e9edef]" style={{ backgroundColor: "#202c33" }}>
                    Done — your short link is{" "}
                    <b style={{ color: "#6ff7b0" }}>sayzio.app/pricing</b> 🎉 Want a
                    QR code for it too?
                  </div>
                  <div className="self-end inline-flex items-center gap-2 max-w-[82%] rounded-2xl rounded-tr-sm px-3 py-2 text-sm text-[#cfe9d8]" style={{ backgroundColor: "#005c4b" }}>
                    <Mic className="w-4 h-4 text-[#8fe9b3]" />
                    <span className="flex items-end gap-[3px] h-4">
                      {[8, 14, 10, 15, 9, 13, 11, 15, 8, 12].map((h, i) => (
                        <span
                          key={i}
                          className="w-[3px] rounded-full"
                          style={{ height: `${h}px`, backgroundColor: "#8fe9b3", opacity: 0.85 }}
                        />
                      ))}
                    </span>
                    <span className="text-[0.7rem]">0:06</span>
                  </div>
                  <div className="self-start max-w-[82%] rounded-2xl rounded-tl-sm px-3 py-2 text-sm text-[#e9edef]" style={{ backgroundColor: "#202c33" }}>
                    Got it 👍 I made a QR code and added it to a contact card with
                    your number. Here you go.
                  </div>
                </div>
              </div>
            </motion.div>
          </div>
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
