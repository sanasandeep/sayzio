const base = import.meta.env.BASE_URL;

export default function Slide155Investorpricing() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="Sayzio" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Pricing</span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.04] tracking-tight">Pricing tiers.</h2>
        
        <div className="mt-[4vh] grid grid-cols-4 gap-[1.4vw]">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="flex items-center justify-between"><div className="font-display text-[1.5vw] font-semibold ">Free</div></div>
            <div className="mt-[0.5vh] font-display text-[2.8vw] font-bold leading-none">$0</div>
            <div className="text-[0.95vw] text-slate-400">forever</div>
            <div className="mt-[2vh] flex flex-col gap-[0.5vh] text-[0.95vw] text-slate-200"><div>&middot; Acquisition layer</div><div>&middot; Conversion to Pro</div></div>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="flex items-center justify-between"><div className="font-display text-[1.5vw] font-semibold ">Pro</div></div>
            <div className="mt-[0.5vh] font-display text-[2.8vw] font-bold leading-none">$12</div>
            <div className="text-[0.95vw] text-slate-400">per month</div>
            <div className="mt-[2vh] flex flex-col gap-[0.5vh] text-[0.95vw] text-slate-200"><div>&middot; Power individuals</div><div>&middot; Highest LTV / CAC ratio</div></div>
          </div>
          <div className="rounded-2xl border border-fuchsia-400/50 bg-gradient-to-br from-fuchsia-500/15 to-blue-500/10 p-[1.6vw] flex flex-col">
            <div className="flex items-center justify-between"><div className="font-display text-[1.5vw] font-semibold text-fuchsia-200">Studio</div><div className="px-[0.6vw] py-[0.2vh] text-[0.8vw] rounded bg-fuchsia-500/30 text-fuchsia-100">popular</div></div>
            <div className="mt-[0.5vh] font-display text-[2.8vw] font-bold leading-none">$29</div>
            <div className="text-[0.95vw] text-slate-400">per month</div>
            <div className="mt-[2vh] flex flex-col gap-[0.5vh] text-[0.95vw] text-slate-200"><div>&middot; Small teams</div><div>&middot; Modal price point</div></div>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="flex items-center justify-between"><div className="font-display text-[1.5vw] font-semibold ">Business</div></div>
            <div className="mt-[0.5vh] font-display text-[2.8vw] font-bold leading-none">$99+</div>
            <div className="text-[0.95vw] text-slate-400">per month</div>
            <div className="mt-[2vh] flex flex-col gap-[0.5vh] text-[0.95vw] text-slate-200"><div>&middot; Agencies, enterprise</div><div>&middot; Highest ARPA</div></div>
          </div>
        </div>
        
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>156 / 189</span></div>
    </div>
  );
}
