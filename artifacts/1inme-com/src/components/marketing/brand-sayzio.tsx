import { motion, useReducedMotion } from "framer-motion";
import {
  Fingerprint,
  Zap,
  Rocket,
  ShieldCheck,
  Boxes,
  Lightbulb,
  type LucideIcon,
} from "lucide-react";
import zioMascot from "@assets/icon_1782443779300.png";

/**
 * "1IN.ME is Sayzio" brand-relationship section — the public-gateway mirror of
 * the Laravel homepage's `home/partials/brand-sayzio.blade.php`. Communicates
 * that 1IN.ME (your unified digital identity) is powered by Sayzio (the smart,
 * scalable, seamless platform engine), capped by the four pillars.
 *
 * Reuses the marketing site's design system (glass-card, grad-text, primary
 * blue palette) and its framer-motion reveal/float helpers so dark/light modes
 * and prefers-reduced-motion handling carry over. Decorative accents are icon
 * tints only — primary UI stays on the blue brand palette.
 */

interface Pillar {
  icon: LucideIcon;
  prefix: string;
  title: string;
  accent: string;
}

const pillars: Pillar[] = [
  { icon: Rocket, prefix: "Built for", title: "Performance", accent: "#3d6bff" },
  { icon: ShieldCheck, prefix: "Engineered for", title: "Reliability", accent: "#1bd4d9" },
  { icon: Boxes, prefix: "Designed for", title: "Scalability", accent: "#e94e8c" },
  { icon: Lightbulb, prefix: "Driven by", title: "Innovation", accent: "#ff8a3c" },
];

export function BrandSayzio() {
  const prefersReducedMotion = useReducedMotion();

  const reveal = (delay: number, from: "up" | "left" | "right" = "up") => {
    if (prefersReducedMotion) return {};
    const offset =
      from === "left" ? { x: -28, y: 0 } : from === "right" ? { x: 28, y: 0 } : { x: 0, y: 20 };
    return {
      initial: { opacity: 0, ...offset },
      whileInView: { opacity: 1, x: 0, y: 0 },
      viewport: { once: true, margin: "-80px" },
      transition: { duration: 0.5, delay },
    };
  };

  const float = prefersReducedMotion
    ? {}
    : {
        animate: { y: [0, -12, 0] },
        transition: { repeat: Infinity, duration: 7, ease: "easeInOut" as const },
      };

  return (
    <section
      className="relative py-24 overflow-hidden"
      aria-labelledby="brand-sayzio-heading"
    >
      <div className="mesh-bg" aria-hidden />
      <div className="container mx-auto px-6 relative z-10">
        {/* Eyebrow + heading */}
        <motion.div className="text-center max-w-3xl mx-auto mb-14 lg:mb-20" {...reveal(0)}>
          <span className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-1.5 text-xs font-semibold text-primary mb-6">
            <span className="relative flex h-2 w-2">
              <span className="absolute inline-flex h-full w-full rounded-full bg-primary/60" />
              <span className="relative inline-flex h-2 w-2 rounded-full bg-primary" />
            </span>
            The platform behind your links
          </span>
          <h2
            id="brand-sayzio-heading"
            className="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.08]"
          >
            <span className="grad-text">1IN.ME</span>
            <span className="text-muted-foreground font-semibold italic px-2">is</span>
            <span className="grad-text">Sayzio</span>
          </h2>
          <p className="mt-5 text-lg text-muted-foreground leading-relaxed">
            <strong className="text-foreground">1IN.ME</strong> is your digital identity,
            unified — and it runs on <strong className="text-foreground">Sayzio</strong>, the
            smart, scalable, seamless platform powering every link, page and QR.
          </p>
        </motion.div>

        {/* Twin brand cards joined by an "is" pill */}
        <div className="relative grid grid-cols-1 lg:grid-cols-[1fr_auto_1fr] gap-6 lg:gap-0 items-stretch">
          {/* 1IN.ME card */}
          <motion.div
            className="glass-card rounded-3xl p-6 sm:p-8 lg:mr-12 relative overflow-hidden"
            {...reveal(0, "right")}
          >
            <div
              aria-hidden
              className="pointer-events-none absolute -top-20 -left-16 h-60 w-60 rounded-full bg-primary/20 blur-3xl"
            />
            <div className="relative flex items-center gap-4 sm:gap-5">
              <span className="shrink-0 inline-flex items-center justify-center w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-br from-[#3d6bff] to-[#6e61ff] text-white shadow-lg">
                <Fingerprint className="w-8 h-8 sm:w-10 sm:h-10" />
              </span>
              <div className="min-w-0">
                <div className="text-3xl sm:text-4xl font-black tracking-tight grad-text">
                  1IN.ME
                </div>
                <div className="mt-1 text-sm sm:text-base text-muted-foreground">
                  Your Digital Identity,{" "}
                  <span className="font-semibold text-primary">Unified.</span>
                </div>
              </div>
            </div>
            <div className="relative mt-6 pt-6 border-t border-border">
              <div className="text-xs font-bold uppercase tracking-[0.18em] mb-1 text-primary">
                All-in-one platform
              </div>
              <p className="text-sm text-muted-foreground leading-relaxed">
                One link for everything — bio pages, short links, QR codes, forms and more, all
                under your handle.
              </p>
            </div>
          </motion.div>

          {/* Center connector ("is" pill) */}
          <div
            className="relative flex lg:flex-col items-center justify-center gap-0 lg:px-2"
            aria-hidden
          >
            <span className="relative z-[2] inline-flex items-center justify-center w-16 h-16 rounded-full bg-gradient-to-br from-[#3d6bff] to-[#6e61ff] text-white font-extrabold tracking-wide shadow-xl">
              is
            </span>
          </div>

          {/* Sayzio card */}
          <motion.div
            className="glass-card rounded-3xl p-6 sm:p-8 lg:ml-12 relative overflow-hidden"
            {...reveal(0.1, "left")}
          >
            <div
              aria-hidden
              className="pointer-events-none absolute -bottom-24 -right-16 h-60 w-60 rounded-full bg-[#6e61ff]/25 blur-3xl"
            />
            <div className="relative flex items-center gap-4 sm:gap-5">
              <motion.img
                src={zioMascot}
                alt="Zio, the Sayzio mascot"
                width={80}
                height={80}
                loading="lazy"
                decoding="async"
                draggable={false}
                className="shrink-0 w-16 h-16 sm:w-20 sm:h-20 object-contain select-none drop-shadow-[0_14px_30px_rgba(110,97,255,0.6)]"
                {...float}
              />
              <div className="min-w-0">
                <div className="text-3xl sm:text-4xl font-black tracking-tight grad-text">
                  Sayzio
                </div>
                <div className="mt-1 text-sm sm:text-base text-muted-foreground">
                  <span className="font-semibold text-foreground">Smart.</span>{" "}
                  <span className="font-semibold text-foreground">Scalable.</span>{" "}
                  <span className="font-semibold text-foreground">Seamless.</span>
                </div>
              </div>
            </div>
            <div className="relative mt-6 pt-6 border-t border-border">
              <div className="text-xs font-bold uppercase tracking-[0.18em] mb-1 text-primary">
                The power behind every experience
              </div>
              <p className="text-sm text-muted-foreground leading-relaxed">
                The engine doing the heavy lifting — analytics, AI, automation and rock-solid
                delivery at any scale.
              </p>
            </div>
          </motion.div>
        </div>

        {/* "Powered by Sayzio" connector */}
        <motion.div className="mt-10 lg:mt-12 flex items-center justify-center" {...reveal(0)}>
          <div className="inline-flex items-center gap-3 px-5 py-2.5 glass-card rounded-full">
            <span className="inline-flex items-center justify-center w-9 h-9 rounded-full bg-gradient-to-br from-[#6e61ff] to-[#3d6bff] text-white shadow-md">
              <Zap className="w-4 h-4" />
            </span>
            <span className="text-xs font-bold uppercase tracking-[0.22em] text-muted-foreground">
              Powered by
            </span>
            <span className="text-base font-extrabold grad-text">Sayzio</span>
          </div>
        </motion.div>

        {/* Four pillars */}
        <div className="mt-12 lg:mt-16 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {pillars.map((pillar, index) => (
            <motion.div
              key={pillar.title}
              className="glass-card rounded-2xl flex items-center gap-3 p-4"
              {...reveal((index % 4) * 0.08)}
            >
              <span
                className="shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-xl"
                style={{ color: pillar.accent, backgroundColor: `${pillar.accent}1f` }}
              >
                <pillar.icon className="w-5 h-5" />
              </span>
              <div className="min-w-0">
                <div className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                  {pillar.prefix}
                </div>
                <div className="text-base font-extrabold text-foreground leading-tight">
                  {pillar.title}
                </div>
              </div>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
