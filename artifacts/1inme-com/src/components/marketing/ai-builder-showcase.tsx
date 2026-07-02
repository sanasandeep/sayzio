import { useEffect, useRef, useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import {
  Sparkles,
  Wand2,
  ArrowRight,
  User,
  Image as ImageIcon,
  Share2,
  Store,
  ClipboardList,
  type LucideIcon,
} from "lucide-react";
import { Eyebrow } from "@/components/marketing/marketing";
import { SIGNUP_URL } from "@/config";

/**
 * Sample prompts that cycle through the typewriter. Mirrors the Laravel home
 * page's `.ai-prompt-card` prompt loop so the "describe it, AI builds it"
 * promise reads the same on both landing surfaces.
 */
const AI_PROMPTS = [
  "Build a page for a Berlin techno artist with tour dates & merch",
  "Create a link-in-bio for a vegan fitness coach",
  "Make a landing page for my coffee shop with a menu & map",
  "Design a portfolio for a freelance photographer",
  "Set up a link-in-bio for a podcast with episodes & socials",
];

interface BuildChip {
  label: string;
  icon: LucideIcon;
  accent: string;
}

/** The blocks the AI "stacks" after a prompt is typed. */
const BUILD_CHIPS: BuildChip[] = [
  { label: "Profile", icon: User, accent: "#3d6bff" },
  { label: "Hero", icon: ImageIcon, accent: "#6e61ff" },
  { label: "Socials", icon: Share2, accent: "#1bd4d9" },
  { label: "Shop", icon: Store, accent: "#ff8a3c" },
  { label: "Form", icon: ClipboardList, accent: "#e94e8c" },
];

type Phase = "ready" | "building" | "built";

/**
 * AI builder prompt showcase — a self-running typewriter that cycles sample
 * prompts and then "stacks" block chips one by one, matching the Laravel home
 * page animation. Respects prefers-reduced-motion by rendering a single,
 * fully-typed prompt in its finished state with no looping.
 */
export function AiBuilderShowcase() {
  const prefersReducedMotion = useReducedMotion();

  const [typed, setTyped] = useState(prefersReducedMotion ? AI_PROMPTS[0] : "");
  const [phase, setPhase] = useState<Phase>(prefersReducedMotion ? "built" : "ready");
  const [visibleChips, setVisibleChips] = useState(
    prefersReducedMotion ? BUILD_CHIPS.length : 0,
  );

  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);

  useEffect(() => {
    if (prefersReducedMotion) return;

    let promptIdx = 0;
    let cancelled = false;

    const wait = (ms: number) =>
      new Promise<void>((resolve) => {
        const id = setTimeout(resolve, ms);
        timers.current.push(id);
      });

    const typePrompt = async () => {
      const text = AI_PROMPTS[promptIdx];
      setTyped("");
      setVisibleChips(0);
      setPhase("ready");

      for (let i = 1; i <= text.length; i++) {
        if (cancelled) return;
        setTyped(text.slice(0, i));
        await wait(32 + Math.random() * 34);
      }

      if (cancelled) return;
      await wait(480);
      if (cancelled) return;

      // Build phase — "stack" the block chips one by one.
      setPhase("building");
      for (let n = 0; n < BUILD_CHIPS.length; n++) {
        await wait(n === 0 ? 140 : 200);
        if (cancelled) return;
        setVisibleChips(n + 1);
      }

      await wait(500);
      if (cancelled) return;
      setPhase("built");

      await wait(2000);
      if (cancelled) return;
      promptIdx = (promptIdx + 1) % AI_PROMPTS.length;
      void typePrompt();
    };

    const startId = setTimeout(() => void typePrompt(), 900);
    timers.current.push(startId);

    const localTimers = timers.current;
    return () => {
      cancelled = true;
      localTimers.forEach(clearTimeout);
      timers.current = [];
    };
  }, [prefersReducedMotion]);

  const reveal = prefersReducedMotion
    ? {}
    : {
        initial: { opacity: 0, y: 20 },
        whileInView: { opacity: 1, y: 0 },
        viewport: { once: true, margin: "-80px" },
        transition: { duration: 0.5 },
      };

  const statusLabel =
    phase === "building" ? "Building…" : phase === "built" ? "Page built" : "Ready";
  const building = phase === "building";

  return (
    <section id="ai-builder" className="relative py-24 overflow-hidden scroll-mt-24">
      <div className="container mx-auto px-6 relative z-10">
        <motion.div className="text-center mb-12 max-w-3xl mx-auto" {...reveal}>
          <Eyebrow>AI page builder</Eyebrow>
          <h2 className="text-3xl lg:text-5xl font-bold tracking-tight mb-4">
            Describe it and <span className="grad-text">AI builds your page</span>
          </h2>
          <p className="text-muted-foreground text-lg">
            Type a sentence and watch Zio stack the blocks — profile, hero,
            socials, shop and forms — into a full Link in Bio, ready to publish.
            Or build it by hand, your call.
          </p>
        </motion.div>

        <motion.div
          className="glass-card rounded-[1.75rem] p-5 sm:p-6 max-w-3xl mx-auto relative overflow-hidden"
          {...reveal}
        >
          <span
            aria-hidden
            className="pointer-events-none absolute inset-0 rounded-[1.75rem] bg-gradient-to-br from-primary/10 via-transparent to-accent-foreground/10"
          />

          {/* Head — badge + live status */}
          <div className="relative flex items-center justify-between mb-4">
            <span className="inline-flex items-center gap-2 rounded-full border border-primary/25 bg-primary/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.04em] text-primary">
              <Wand2 className="w-3.5 h-3.5" />
              AI builder
            </span>
            <span
              className={`inline-flex items-center gap-2 text-xs font-semibold ${
                building ? "text-primary" : "text-muted-foreground"
              }`}
            >
              <span className="ai-status-dot" data-building={building} aria-hidden />
              {statusLabel}
            </span>
          </div>

          {/* Prompt input row */}
          <div className="relative flex items-center gap-3 rounded-2xl border border-border/60 bg-background/60 px-4 py-3">
            <Sparkles className="w-4 h-4 shrink-0 text-primary" aria-hidden />
            <div
              className="flex-1 min-w-0 text-sm sm:text-[0.95rem] font-medium leading-relaxed text-foreground break-words"
              aria-hidden
            >
              {typed}
              {!prefersReducedMotion && <span className="ai-caret" />}
            </div>
            <a
              href={SIGNUP_URL}
              className={`shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-xs font-bold text-primary-foreground shadow-lg shadow-primary/30 transition ${
                building ? "scale-95 brightness-110" : "hover:opacity-90"
              }`}
            >
              Generate <ArrowRight className="w-3 h-3" />
            </a>
          </div>

          {/* Build chips — AI stacks your blocks */}
          <div
            className="relative mt-4 flex flex-wrap items-center gap-2 min-h-[30px]"
            aria-hidden
          >
            <span className="text-xs font-semibold text-muted-foreground">
              AI stacks your blocks:
            </span>
            {BUILD_CHIPS.map((chip, i) => {
              const shown = i < visibleChips;
              return (
                <span
                  key={chip.label}
                  className="inline-flex items-center gap-1.5 rounded-full border border-border/60 bg-foreground/[0.04] px-2.5 py-1 text-xs font-semibold text-foreground/80 transition-all duration-300 ease-out"
                  style={{
                    opacity: shown ? 1 : 0,
                    transform: shown
                      ? "translateY(0) scale(1)"
                      : "translateY(6px) scale(0.94)",
                  }}
                >
                  <chip.icon className="w-3 h-3" style={{ color: chip.accent }} />
                  {chip.label}
                </span>
              );
            })}
          </div>
        </motion.div>
      </div>
    </section>
  );
}
