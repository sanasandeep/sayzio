const base = import.meta.env.BASE_URL;

export default function Slide06BrandPillars() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(124,58,237,0.18),transparent_60%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Brand pillars</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <span className="text-[1vw] uppercase tracking-[0.3em] text-fuchsia-300">What we believe</span>
        <h2 className="mt-[1.5vh] font-display text-[4vw] font-bold leading-[1.02] tracking-tight max-w-[60vw]">Three principles that shape every decision.</h2>

        <div className="mt-[6vh] grid grid-cols-3 gap-[2.5vw]">
          <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-[2.2vw]">
            <div className="font-display text-[1vw] uppercase tracking-[0.3em] text-violet-300">01</div>
            <div className="mt-[1vh] font-display text-[2.4vw] font-bold tracking-tight">Owned, not rented</div>
            <p className="mt-[1.5vh] text-[1.2vw] text-slate-300 leading-snug">Custom domains, exportable data, portable identity. Your audience is yours.</p>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-[2.2vw]">
            <div className="font-display text-[1vw] uppercase tracking-[0.3em] text-fuchsia-300">02</div>
            <div className="mt-[1vh] font-display text-[2.4vw] font-bold tracking-tight">Calm by default</div>
            <p className="mt-[1.5vh] text-[1.2vw] text-slate-300 leading-snug">Software that disappears when it&rsquo;s done. No dark patterns, no upsell anxiety.</p>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-[2.2vw]">
            <div className="font-display text-[1vw] uppercase tracking-[0.3em] text-cyan-300">03</div>
            <div className="mt-[1vh] font-display text-[2.4vw] font-bold tracking-tight">Intelligent, not extractive</div>
            <p className="mt-[1.5vh] text-[1.2vw] text-slate-300 leading-snug">AI that earns its place by saving you time, not by selling your data.</p>
          </div>
        </div>
      </div>

      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>06 / 84</span></div>
    </div>
  );
}
