import { useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { Sparkles, RotateCw } from "lucide-react";
import { Eyebrow } from "@/components/marketing/marketing";

interface AiTile {
  /** Public-path image icon (illustration, not a line icon). */
  icon: string;
  title: string;
  /** One-line front-of-tile hook. */
  desc: string;
  /** Heading shown on the flipped back face. */
  backTitle: string;
  /** The additional AI features grouped under this tile. */
  features: string[];
}

/**
 * Every distinct AI capability, grouped sensibly across exactly four square
 * tiles. The tile front leads with the flagship feature; the back lists the
 * rest of the family. Content lives here as a local array — no backend/content
 * API is involved (marketing site only).
 */
const AI_TILES: AiTile[] = [
  {
    icon: "ai-tiles/ai-builder.png",
    title: "AI Builder",
    desc: "Builds your whole Link in Bio from a single prompt.",
    backTitle: "Also handles",
    features: [
      "Writes your copy in your voice",
      "Picks an on-brand theme",
      "Lays out the right blocks",
    ],
  },
  {
    icon: "ai-tiles/ai-chat.png",
    title: "AI Chat & Agents",
    desc: "A 24/7 chatbot that answers visitors on your page.",
    backTitle: "Across the suite",
    features: [
      "AI Chat on your page",
      "Embeddable AI Widget for any site",
      "Persona Generator in your tone",
      "Knowledge Bases your AI can draw on",
    ],
  },
  {
    icon: "ai-tiles/ai-voice.png",
    title: "AI Voice & Agent",
    desc: "An AI voice assistant that answers your calls.",
    backTitle: "Goes further",
    features: [
      "Voice receptionist (STT · LLM · TTS)",
      "Qualifies callers and books meetings",
      "Multi-step AI Agent playbooks",
    ],
  },
  {
    icon: "ai-tiles/ai-coach.png",
    title: "AI Coach & Tools",
    desc: "A Performance Coach that turns numbers into next steps.",
    backTitle: "Plus everyday tools",
    features: [
      "Account Assistant for plain-English insights",
      "AI Resume — import, tailor, cover letter",
      "AI Card & Brochure scanner",
    ],
  },
];

type RevealProps = ReturnType<(d: number) => Record<string, unknown>>;

function FlipTile({ tile, reveal, delay }: { tile: AiTile; reveal: (d: number) => RevealProps; delay: number }) {
  const [flipped, setFlipped] = useState(false);
  const iconSrc = `${import.meta.env.BASE_URL}${tile.icon}`;

  return (
    <motion.div className="flip-tile aspect-square w-full" data-flipped={flipped} {...reveal(delay)}>
      <div className="flip-tile-inner">
        {/* ── Front ── */}
        <div className="flip-face glass-card rounded-3xl p-6 flex flex-col items-center justify-center text-center gap-4">
          <span
            aria-hidden
            className="absolute inset-0 -z-10 rounded-3xl bg-gradient-to-br from-primary/10 via-transparent to-transparent"
          />
          <img
            src={iconSrc}
            alt=""
            aria-hidden
            draggable={false}
            className="w-24 h-24 lg:w-28 lg:h-28 object-contain drop-shadow-xl select-none"
          />
          <div>
            <h3 className="text-lg font-semibold mb-1.5">{tile.title}</h3>
            <p className="text-sm text-muted-foreground leading-relaxed">{tile.desc}</p>
          </div>
          <span className="mt-1 inline-flex items-center gap-1.5 text-xs font-semibold text-primary">
            <RotateCw className="w-3.5 h-3.5" />
            Flip for more
          </span>
        </div>

        {/* ── Back ── */}
        <div className="flip-face flip-face-back glass-card rounded-3xl p-6 flex flex-col">
          <span
            aria-hidden
            className="absolute inset-0 -z-10 rounded-3xl bg-gradient-to-br from-primary/15 via-transparent to-accent-foreground/10"
          />
          <div className="flex items-center gap-2 mb-4">
            <span className="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
              <Sparkles className="w-4 h-4" />
            </span>
            <div className="leading-tight">
              <p className="text-xs font-bold uppercase tracking-wider text-primary">{tile.backTitle}</p>
              <p className="text-sm font-semibold">{tile.title}</p>
            </div>
          </div>
          <ul className="space-y-2.5 text-left">
            {tile.features.map((feature) => (
              <li key={feature} className="flex items-start gap-2.5 text-sm font-medium leading-snug">
                <span className="mt-1 w-1.5 h-1.5 rounded-full bg-primary shrink-0" />
                <span>{feature}</span>
              </li>
            ))}
          </ul>
        </div>
      </div>

      {/* Full-tile accessible toggle (handles tap; hover handled by CSS). */}
      <button
        type="button"
        onClick={() => setFlipped((f) => !f)}
        aria-pressed={flipped}
        aria-label={`${tile.title}: ${flipped ? "show summary" : "show more AI features"}`}
        className="absolute inset-0 z-10 rounded-3xl focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/60 focus-visible:ring-offset-2 focus-visible:ring-offset-background"
      />
    </motion.div>
  );
}

/**
 * Showcase of every Sayzio AI capability as a row of four flippable square
 * tiles. Front = flagship feature with an illustrated icon; back = the rest of
 * the AI family grouped under it. Flips on hover (pointer devices) and on tap,
 * with a reduced-motion fallback that swaps faces without spinning.
 */
export function AiFlipTiles() {
  const prefersReducedMotion = useReducedMotion();

  const reveal = (delay: number): RevealProps =>
    prefersReducedMotion
      ? {}
      : {
          initial: { opacity: 0, y: 20 },
          whileInView: { opacity: 1, y: 0 },
          viewport: { once: true, margin: "-80px" },
          transition: { duration: 0.5, delay },
        };

  return (
    <section id="ai-toolkit" className="relative py-24 overflow-hidden scroll-mt-24">
      <div className="container mx-auto px-6 relative z-10">
        <motion.div className="text-center mb-16 max-w-3xl mx-auto" {...reveal(0)}>
          <Eyebrow>The full AI toolkit</Eyebrow>
          <h2 className="text-3xl lg:text-5xl font-bold tracking-tight mb-4">
            Four ways AI <span className="grad-text">does the work</span> for you
          </h2>
          <p className="text-muted-foreground text-lg">
            From building your page to answering your calls — flip each tile to
            see everything the AI suite quietly handles on your behalf.
          </p>
        </motion.div>

        <div className="grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
          {AI_TILES.map((tile, index) => (
            <FlipTile key={tile.title} tile={tile} reveal={reveal} delay={(index % 4) * 0.08} />
          ))}
        </div>
      </div>
    </section>
  );
}
