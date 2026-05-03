export default function Slide18BiolinksThemes() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Biolinks · Themes</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight max-w-[60vw]">Themes that look designed, not templated.</h2>
        <p className="mt-[2vh] text-[1.4vw] text-slate-300 max-w-[55vw]">Curated themes, custom CSS, brand fonts, and palette tokens that cascade everywhere.</p>

        <div className="mt-[5vh] grid grid-cols-4 gap-[1.5vw]">
          <div className="rounded-2xl border border-white/10 bg-gradient-to-br from-violet-500 to-fuchsia-500 h-[34vh] p-[1.4vw] flex flex-col justify-end"><div className="font-display text-[1.6vw] font-bold">Aurora</div><div className="text-[1vw] text-white/80">Bold gradient</div></div>
          <div className="rounded-2xl border border-white/10 bg-[#0e0e1a] h-[34vh] p-[1.4vw] flex flex-col justify-end"><div className="font-display text-[1.6vw] font-bold">Onyx</div><div className="text-[1vw] text-slate-400">Editorial dark</div></div>
          <div className="rounded-2xl border border-amber-200/30 bg-[#f5e9d3] text-[#3a2a1a] h-[34vh] p-[1.4vw] flex flex-col justify-end"><div className="font-display text-[1.6vw] font-bold">Linen</div><div className="text-[1vw] opacity-70">Warm minimal</div></div>
          <div className="rounded-2xl border border-cyan-300/30 bg-gradient-to-br from-cyan-500/30 to-emerald-400/20 h-[34vh] p-[1.4vw] flex flex-col justify-end"><div className="font-display text-[1.6vw] font-bold">Lagoon</div><div className="text-[1vw] text-white/80">Crisp tech</div></div>
        </div>
        <p className="mt-[3vh] text-[1.2vw] text-slate-400">Every theme respects your brand palette &mdash; the colours stay yours.</p>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>18 / 84</span></div>
    </div>
  );
}
