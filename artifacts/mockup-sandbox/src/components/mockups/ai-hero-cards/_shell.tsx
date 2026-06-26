import React from "react";

export const tokens = {
  blue: "#3d6bff",
  cyan: "#1bd4d9",
  green: "#1ed760",
  bg: "#07070f",
  screen: "#0a0a14",
};

const ASSET_BASE = (import.meta.env.BASE_URL || "/").replace(/\/?$/, "/");
export const img = (name: string) => `${ASSET_BASE}images/ai-cards/${name}`;

const FONTS = `
  @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap');
  .font-grotesk { font-family: 'Space Grotesk', sans-serif; }
  .stage-sans { font-family: 'Inter', sans-serif; }
  .no-sb::-webkit-scrollbar { display: none; }
  .no-sb { scrollbar-width: none; }
  @keyframes pulse-dot { 0%,100% { opacity: 1; } 50% { opacity: .35; } }
`;

export function PhoneStage({
  children,
  chips,
  accent = tokens.blue,
}: {
  children: React.ReactNode;
  chips?: React.ReactNode;
  accent?: string;
}) {
  return (
    <div
      className="stage-sans flex min-h-[100dvh] w-full items-center justify-center overflow-hidden"
      style={{
        background:
          "radial-gradient(120% 120% at 50% 0%, #11111f 0%, #08080f 55%, #050509 100%)",
      }}
    >
      <style dangerouslySetInnerHTML={{ __html: FONTS }} />
      <div className="relative" style={{ width: 300, height: 620 }}>
        <div
          className="absolute rounded-full blur-3xl"
          style={{
            width: 260,
            height: 260,
            background: accent,
            opacity: 0.38,
            top: -50,
            right: -70,
            zIndex: 0,
          }}
        />
        <div
          className="absolute rounded-full blur-3xl"
          style={{
            width: 240,
            height: 240,
            background: tokens.cyan,
            opacity: 0.22,
            bottom: -40,
            left: -80,
            zIndex: 0,
          }}
        />
        <Phone>{children}</Phone>
        {chips}
      </div>
    </div>
  );
}

function Phone({ children }: { children: React.ReactNode }) {
  return (
    <div
      className="relative h-full w-full rounded-[46px] p-[10px]"
      style={{
        background: "linear-gradient(160deg,#1c1c2a,#0a0a12)",
        border: "1px solid rgba(255,255,255,.14)",
        boxShadow:
          "0 50px 90px -30px rgba(0,0,0,.85), inset 0 1px 0 rgba(255,255,255,.06)",
        zIndex: 1,
      }}
    >
      <div
        className="absolute left-1/2 top-[14px] z-20 h-[24px] w-[96px] -translate-x-1/2 rounded-full bg-black"
        style={{ boxShadow: "inset 0 0 0 1px rgba(255,255,255,.05)" }}
      />
      <div
        className="h-full w-full overflow-hidden rounded-[38px]"
        style={{ background: tokens.screen }}
      >
        {children}
      </div>
    </div>
  );
}

export function StatusBar({ label = "9:41" }: { label?: string }) {
  return (
    <div className="flex items-center justify-between px-6 pt-3 pb-1 text-[11px] font-semibold text-white/85">
      <span className="font-grotesk">{label}</span>
      <div className="flex items-center gap-1.5 opacity-80">
        <svg width="15" height="11" viewBox="0 0 18 12" fill="currentColor">
          <rect x="0" y="8" width="3" height="4" rx="1" />
          <rect x="5" y="5" width="3" height="7" rx="1" />
          <rect x="10" y="2" width="3" height="10" rx="1" />
          <rect x="15" y="0" width="3" height="12" rx="1" opacity="0.4" />
        </svg>
        <svg width="14" height="11" viewBox="0 0 16 12" fill="currentColor">
          <path d="M8 11.5 0.7 4.2a10.3 10.3 0 0 1 14.6 0L8 11.5Z" />
        </svg>
        <div className="flex h-[11px] w-[22px] items-center rounded-[3px] border border-white/50 p-[1.5px]">
          <div className="h-full w-[70%] rounded-[1px] bg-white" />
        </div>
      </div>
    </div>
  );
}

export type ChipTone = "glass" | "green" | "blue";

export function Chip({
  children,
  style,
  tone = "glass",
}: {
  children: React.ReactNode;
  style?: React.CSSProperties;
  tone?: ChipTone;
}) {
  const tones: Record<ChipTone, React.CSSProperties> = {
    glass: {
      background: "rgba(20,20,32,.72)",
      border: "1px solid rgba(255,255,255,.14)",
      color: "#fff",
    },
    green: {
      background: "rgba(30,215,96,.16)",
      border: "1px solid rgba(30,215,96,.4)",
      color: tokens.green,
    },
    blue: {
      background: "rgba(61,107,255,.18)",
      border: "1px solid rgba(61,107,255,.45)",
      color: "#bcd0ff",
    },
  };
  return (
    <div
      className="absolute z-30 flex items-center gap-2 whitespace-nowrap rounded-2xl px-3 py-2 text-[11px] font-semibold shadow-xl backdrop-blur-xl"
      style={{ ...tones[tone], ...style }}
    >
      {children}
    </div>
  );
}

export function BuilderChip({ style }: { style?: React.CSSProperties }) {
  return (
    <Chip tone="glass" style={style}>
      <span
        className="flex h-6 w-6 items-center justify-center rounded-full"
        style={{ background: tokens.blue }}
      >
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
          <path d="M20 6 9 17l-5-5" />
        </svg>
      </span>
      <span className="leading-tight">
        <span className="block text-[8px] font-bold uppercase tracking-[0.18em] text-white/55">
          AI Builder
        </span>
        <span className="block text-white">Page built in 18s</span>
      </span>
    </Chip>
  );
}
