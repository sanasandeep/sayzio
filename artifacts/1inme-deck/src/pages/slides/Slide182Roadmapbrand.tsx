const base = import.meta.env.BASE_URL;

export default function Slide182Roadmapbrand() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Roadmap</span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.04] tracking-tight">Roadmap · Brand &amp; design system.</h2>
        
        <div className="mt-[4vh] grid grid-cols-3 gap-[1.5vw] flex-1">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="text-[0.95vw] uppercase tracking-[0.3em] text-fuchsia-200">Now</div>
            <div className="mt-[0.5vh] font-display text-[1.8vw] font-semibold">Brand kits</div>
            <ul className="mt-[2vh] space-y-[0.8vh] text-[1.05vw] text-slate-300 leading-snug"><li>&middot; Per-workspace kits</li><li>&middot; Logo + color tokens</li><li>&middot; Font hosting</li></ul>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="text-[0.95vw] uppercase tracking-[0.3em] text-fuchsia-200">Next</div>
            <div className="mt-[0.5vh] font-display text-[1.8vw] font-semibold">Theme studio</div>
            <ul className="mt-[2vh] space-y-[0.8vh] text-[1.05vw] text-slate-300 leading-snug"><li>&middot; Visual theme editor</li><li>&middot; Bio + email parity</li><li>&middot; Dark / light variants</li></ul>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="text-[0.95vw] uppercase tracking-[0.3em] text-fuchsia-200">Later</div>
            <div className="mt-[0.5vh] font-display text-[1.8vw] font-semibold">Design API</div>
            <ul className="mt-[2vh] space-y-[0.8vh] text-[1.05vw] text-slate-300 leading-snug"><li>&middot; Programmatic theming</li><li>&middot; Tokens via API</li><li>&middot; Partner-built themes</li></ul>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>182 / 188</span></div>
    </div>
  );
}
