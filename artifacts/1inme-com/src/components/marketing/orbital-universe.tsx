import { useState } from "react";
import { useReducedMotion } from "framer-motion";
import {
  Wand2,
  Gauge,
  Bot,
  PhoneCall,
  Workflow,
  Code2,
  Link2,
  QrCode,
  BarChart3,
  Sparkles,
  type LucideIcon,
} from "lucide-react";
import zioMascot from "@assets/icon_1782443779300.png";
import { featuresCategories } from "@/content/features";

interface Planet {
  title: string;
  desc: string;
  icon: LucideIcon;
  accent: string;
  /** Angle on its ring, in degrees (0 = right, -90 = top). */
  angle: number;
  /** Ring radius as a percentage of the square stage. */
  radius: number;
  /** Visual ring this planet belongs to (drives float cadence). */
  ring: "inner" | "outer";
}

type PlanetStyle = Pick<Planet, "icon" | "accent" | "angle" | "radius" | "ring">;

/**
 * Pull a planet's title + description from the canonical marketing feature
 * catalog (`features.ts`) so the orbital copy stays in sync with the rest of
 * the site instead of drifting from a second hardcoded list.
 */
function sourced(categoryId: string, itemTitle: string, style: PlanetStyle): Planet {
  const item = featuresCategories
    .find((c) => c.id === categoryId)
    ?.items.find((i) => i.title === itemTitle);
  return { title: item?.title ?? itemTitle, desc: item?.description ?? "", ...style };
}

/**
 * The orbiting feature set. Six flagship AI capabilities ride the outer ring;
 * three everyday tools sit closer in. Copy is sourced from the canonical
 * feature catalog so the marketing story stays in sync — presentational only.
 *
 * "AI Page Builder" and "AI Performance Coach" are brand-level AI capabilities
 * (see this page's meta description) with no itemised entry in `features.ts`,
 * so their short copy is defined inline; every other planet pulls from the
 * catalog via `sourced()`.
 */
const PLANETS: Planet[] = [
  // ── Outer ring: the AI suite ──
  { title: "AI Page Builder", desc: "Describe it in a sentence — AI assembles your whole page.", icon: Wand2, accent: "#6e61ff", angle: -90, radius: 43, ring: "outer" },
  { title: "AI Performance Coach", desc: "Reads your analytics and tells you what to fix next.", icon: Gauge, accent: "#3d6bff", angle: -30, radius: 43, ring: "outer" },
  sourced("ai-suite", "Chat Widgets", { icon: Bot, accent: "#1bd4d9", angle: 30, radius: 43, ring: "outer" }),
  sourced("ai-suite", "AI Voice Assistant", { icon: PhoneCall, accent: "#ff8a3c", angle: 90, radius: 43, ring: "outer" }),
  sourced("ai-suite", "AI Agents", { icon: Workflow, accent: "#e94e8c", angle: 150, radius: 43, ring: "outer" }),
  sourced("ai-suite", "AI Widget", { icon: Code2, accent: "#3d6bff", angle: 210, radius: 43, ring: "outer" }),
  // ── Inner ring: everyday tools (copy sourced from the feature catalog) ──
  sourced("links", "Short URLs", { icon: Link2, accent: "#6e61ff", angle: 60, radius: 26, ring: "inner" }),
  sourced("qr", "Per-link QR codes", { icon: QrCode, accent: "#1bd4d9", angle: 180, radius: 26, ring: "inner" }),
  sourced("analytics", "Visitor analytics", { icon: BarChart3, accent: "#3d6bff", angle: 300, radius: 26, ring: "inner" }),
];

export type OrbitalVariant = "full" | "compact";

/** Stage edge length (px) per variant. radius/sizing are %/vw based so they scale. */
const STAGE_SIZE: Record<OrbitalVariant, number> = { full: 480, compact: 320 };

/** Re-space a ring's planets evenly so a trimmed subset stays balanced. */
function evenlySpaced(planets: Planet[], start = -90): Planet[] {
  const step = 360 / planets.length;
  return planets.map((p, i) => ({ ...p, angle: start + i * step }));
}

/**
 * Per-variant planet sets. "full" keeps the canonical nine; "compact" trims to a
 * lighter five (3 outer AI flagships + 2 everyday tools), each ring re-spaced so
 * the smaller stage stays balanced.
 */
const PLANETS_BY_VARIANT: Record<OrbitalVariant, Planet[]> = {
  full: PLANETS,
  compact: [
    ...evenlySpaced(PLANETS.filter((p) => p.ring === "outer").filter((_, i) => i % 2 === 0)),
    ...evenlySpaced(PLANETS.filter((p) => p.ring === "inner").slice(0, 2)),
  ],
};

function position(angle: number, radius: number) {
  const rad = (angle * Math.PI) / 180;
  return {
    left: `${50 + radius * Math.cos(rad)}%`,
    top: `${50 + radius * Math.sin(rad)}%`,
  };
}

function PlanetNode({ planet, index, active, onActivate, onClear }: {
  planet: Planet;
  index: number;
  active: boolean;
  onActivate: () => void;
  onClear: () => void;
}) {
  const { left, top } = position(planet.angle, planet.radius);
  const isOuter = planet.ring === "outer";
  // Point the tooltip inward so it never escapes the stage.
  const below = planet.angle > 0 && planet.angle < 180; // bottom half
  const x = 50 + planet.radius * Math.cos((planet.angle * Math.PI) / 180);
  const tx = x < 35 ? "-12%" : x > 65 ? "-88%" : "-50%";

  return (
    <div className="orbit-planet" style={{ left, top }} data-active={active}>
      <div className="orbit-planet-float" style={{ animationDelay: `${(index % 4) * -1.4}s` }}>
        <button
          type="button"
          onMouseEnter={onActivate}
          onMouseLeave={onClear}
          onFocus={onActivate}
          onBlur={onClear}
          onClick={onActivate}
          aria-label={`${planet.title}: ${planet.desc}`}
          className={`orbit-planet-btn glass-card flex items-center justify-center rounded-full transition-transform duration-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/70 ${
            isOuter ? "h-[clamp(44px,13vw,64px)] w-[clamp(44px,13vw,64px)]" : "h-[clamp(36px,10vw,50px)] w-[clamp(36px,10vw,50px)]"
          }`}
          style={{
            boxShadow: active ? `0 0 0 2px ${planet.accent}, 0 12px 30px -10px ${planet.accent}aa` : undefined,
          }}
        >
          <span
            aria-hidden
            className="absolute inset-0 -z-10 rounded-full opacity-60"
            style={{ background: `radial-gradient(circle at 50% 35%, ${planet.accent}33, transparent 70%)` }}
          />
          <planet.icon
            className={isOuter ? "h-[clamp(20px,6vw,28px)] w-[clamp(20px,6vw,28px)]" : "h-[clamp(16px,5vw,22px)] w-[clamp(16px,5vw,22px)]"}
            style={{ color: planet.accent }}
          />
        </button>

        {/* Hover / focus tooltip — points inward so it stays on-stage */}
        <div
          role="tooltip"
          className="orbit-tooltip glass-card pointer-events-none absolute z-30 w-[180px] rounded-2xl p-3 text-left"
          style={{
            left: "50%",
            transform: `translateX(${tx})`,
            ...(below ? { bottom: "calc(100% + 10px)" } : { top: "calc(100% + 10px)" }),
          }}
        >
          <p className="text-sm font-semibold leading-tight" style={{ color: planet.accent }}>
            {planet.title}
          </p>
          <p className="mt-1 text-xs leading-snug text-muted-foreground">{planet.desc}</p>
        </div>
      </div>
    </div>
  );
}

/**
 * The hero's right-hand visual: Zio centered as the hub with feature "planets"
 * orbiting on concentric, continuously-rotating dotted rings. Rings spin on an
 * infinite loop at varying speeds/directions; planets gently float and reveal a
 * name + one-line description on hover/focus. Everything degrades to a static,
 * legible layout under `prefers-reduced-motion`.
 */
export function OrbitalUniverse({ variant = "full" }: { variant?: OrbitalVariant } = {}) {
  const [active, setActive] = useState<number | null>(null);
  const prefersReducedMotion = useReducedMotion();
  const planets = PLANETS_BY_VARIANT[variant];
  const isCompact = variant === "compact";

  return (
    <div
      className="orbit-stage relative aspect-square max-w-full"
      style={{ width: STAGE_SIZE[variant] }}
      data-variant={variant}
      data-reduced={prefersReducedMotion ? "true" : "false"}
    >
      {/* ── Concentric dotted rings (infinite spin, varying speed/direction) ── */}
      <svg className="orbit-rings absolute inset-0 h-full w-full" viewBox="0 0 100 100" aria-hidden fill="none">
        <circle className="orbit-ring orbit-ring--3" cx="50" cy="50" r="43" pathLength={100} />
        <circle className="orbit-ring orbit-ring--2" cx="50" cy="50" r="34.5" pathLength={100} />
        <circle className="orbit-ring orbit-ring--1" cx="50" cy="50" r="26" pathLength={100} />
        <circle className="orbit-ring-faint" cx="50" cy="50" r="43" />
        <circle className="orbit-ring-faint" cx="50" cy="50" r="26" />
      </svg>

      {/* ── Center hub: glow halo + Zio ── */}
      <div className="absolute left-1/2 top-1/2 z-20 -translate-x-1/2 -translate-y-1/2">
        <div className={`relative ${isCompact ? "w-[clamp(76px,22vw,112px)]" : "w-[clamp(108px,30vw,168px)]"}`}>
          <div aria-hidden className="absolute inset-0 -z-10 scale-[1.55] rounded-full bg-primary/30 blur-[44px]" />
          <div aria-hidden className="absolute inset-0 -z-10 scale-110 rounded-full bg-accent-foreground/20 blur-2xl" />
          <img
            src={zioMascot}
            alt="Zio, the Sayzio AI mascot, at the center of the universe"
            className="orbit-zio w-full select-none drop-shadow-2xl"
            draggable={false}
          />
        </div>
        <div className="mt-2 flex justify-center">
          <span className="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary backdrop-blur">
            <Sparkles className="h-3.5 w-3.5" />
            Zio runs it all
          </span>
        </div>
      </div>

      {/* ── Feature planets ── */}
      {planets.map((planet, i) => (
        <PlanetNode
          key={planet.title}
          planet={planet}
          index={i}
          active={active === i}
          onActivate={() => setActive(i)}
          onClear={() => setActive((cur) => (cur === i ? null : cur))}
        />
      ))}
    </div>
  );
}
