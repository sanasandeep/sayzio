const base = import.meta.env.BASE_URL;

export default function SubdeckToc() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="Sayzio" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Table of Contents</span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight">Table of contents</h2>
        <p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[60vw]">Sub-deck of 2 sections, exported from the master deck.</p>
        <div className="mt-[4vh] grid grid-cols-2 gap-[1.5vw]">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.5vw] flex items-start gap-[1.2vw]">
            <div className="font-display text-[1.6vw] font-bold text-violet-300 w-[3vw]">01</div>
            <div className="flex-1"><div className="font-display text-[1.5vw] font-semibold">Sales Presentation</div><div className="mt-[0.4vh] text-[1vw] text-slate-400">Problem, pitch, ROI, pricing, next steps.</div></div>
            <div className="text-[1vw] text-fuchsia-200 font-mono whitespace-nowrap">3 – 23</div>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.5vw] flex items-start gap-[1.2vw]">
            <div className="font-display text-[1.6vw] font-bold text-violet-300 w-[3vw]">02</div>
            <div className="flex-1"><div className="font-display text-[1.5vw] font-semibold">Investor Pitch</div><div className="mt-[0.4vh] text-[1vw] text-slate-400">Vision, market, model, team, ask.</div></div>
            <div className="text-[1vw] text-fuchsia-200 font-mono whitespace-nowrap">24 – 44</div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>2 / 44</span></div>
    </div>
  );
}
