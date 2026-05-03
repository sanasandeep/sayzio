export default function Slide70Pixels() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(236,72,153,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Tracking pixels</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">Your ads platform, fed automatically.</h2>
        <p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[55vw]">Drop in pixel IDs, choose which events to forward, and 1INME handles the rest &mdash; with proper consent management.</p>

        <div className="mt-[5vh] grid grid-cols-3 gap-[1.5vw]">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="font-display text-[1.6vw] font-semibold">Meta Pixel</div><div className="text-[1.05vw] text-slate-300 mt-[0.5vh]">Page view, lead, purchase &mdash; deduped server-side.</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="font-display text-[1.6vw] font-semibold">Google Ads &amp; GA4</div><div className="text-[1.05vw] text-slate-300 mt-[0.5vh]">Conversions, GA4 events, and enhanced consent.</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="font-display text-[1.6vw] font-semibold">TikTok &amp; LinkedIn</div><div className="text-[1.05vw] text-slate-300 mt-[0.5vh]">Pixel + Conversions API for both.</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="font-display text-[1.6vw] font-semibold">Pinterest &amp; Snap</div><div className="text-[1.05vw] text-slate-300 mt-[0.5vh]">Native pixel support with event deduplication.</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="font-display text-[1.6vw] font-semibold">Custom HTML</div><div className="text-[1.05vw] text-slate-300 mt-[0.5vh]">Drop in any vendor script with consent gating.</div></div>
          <div className="rounded-2xl border border-fuchsia-400/40 bg-fuchsia-500/10 p-[1.8vw]"><div className="font-display text-[1.6vw] font-semibold text-fuchsia-200">Consent Mode v2</div><div className="text-[1.05vw] text-fuchsia-100/80 mt-[0.5vh]">Region-aware banner, fully audit-ready.</div></div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>70 / 84</span></div>
    </div>
  );
}
