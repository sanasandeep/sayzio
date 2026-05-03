const base = import.meta.env.BASE_URL;

export default function Slide61CreditPacks() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">AI Credit packs</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">Top up when the month gets busy.</h2>
        <p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[55vw]">Credit packs never expire. Use them across every AI surface in 1INME.</p>

        <div className="mt-[5vh] grid grid-cols-4 gap-[1.5vw]">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw] flex flex-col"><div className="text-[1vw] uppercase tracking-[0.25em] text-violet-300">Starter</div><div className="font-display text-[3vw] font-bold mt-[0.5vh] leading-none">5,000</div><div className="text-[1vw] text-slate-400">credits</div><div className="mt-[2vh] font-display text-[2vw] font-bold">$9</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw] flex flex-col"><div className="text-[1vw] uppercase tracking-[0.25em] text-fuchsia-300">Maker</div><div className="font-display text-[3vw] font-bold mt-[0.5vh] leading-none">25,000</div><div className="text-[1vw] text-slate-400">credits</div><div className="mt-[2vh] font-display text-[2vw] font-bold">$39</div></div>
          <div className="rounded-2xl border border-fuchsia-400/40 bg-fuchsia-500/10 p-[1.8vw] flex flex-col"><div className="text-[1vw] uppercase tracking-[0.25em] text-fuchsia-200">Studio</div><div className="font-display text-[3vw] font-bold mt-[0.5vh] leading-none">100,000</div><div className="text-[1vw] text-slate-400">credits</div><div className="mt-[2vh] font-display text-[2vw] font-bold text-fuchsia-200">$129</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw] flex flex-col"><div className="text-[1vw] uppercase tracking-[0.25em] text-cyan-300">Scale</div><div className="font-display text-[3vw] font-bold mt-[0.5vh] leading-none">500,000</div><div className="text-[1vw] text-slate-400">credits</div><div className="mt-[2vh] font-display text-[2vw] font-bold">$549</div></div>
        </div>

        <p className="mt-[4vh] text-[1.1vw] text-slate-400">All packs include the same model access. No tier locks behind credit volume.</p>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>61 / 84</span></div>
    </div>
  );
}
