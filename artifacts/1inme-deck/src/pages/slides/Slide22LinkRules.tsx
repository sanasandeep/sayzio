export default function Slide22LinkRules() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_right,rgba(124,58,237,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Smart routing rules</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-5 flex flex-col justify-center">
          <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight">Build rules in plain English.</h2>
          <p className="mt-[2.5vh] text-[1.3vw] text-slate-300 max-w-[28vw] leading-snug">Stack as many conditions as you need. Every rule is testable before you ship.</p>
        </div>
        <div className="col-span-7 flex items-center">
          <div className="w-full rounded-2xl border border-white/10 bg-[#0d0d18] p-[1.6vw] flex flex-col gap-[1.4vh] text-[1.1vw]">
            <div className="rounded-lg bg-white/[0.04] border border-white/10 p-[1.2vw]"><span className="text-fuchsia-300 font-semibold">When</span> visitor.country = <span className="text-emerald-300">"US"</span> <span className="text-slate-400">AND</span> device = <span className="text-emerald-300">"iOS"</span> <span className="text-fuchsia-300 font-semibold">&rarr; go to</span> apps.apple.com/us/...</div>
            <div className="rounded-lg bg-white/[0.04] border border-white/10 p-[1.2vw]"><span className="text-fuchsia-300 font-semibold">When</span> visitor.country = <span className="text-emerald-300">"DE"</span> <span className="text-fuchsia-300 font-semibold">&rarr; go to</span> 1inme.com/de</div>
            <div className="rounded-lg bg-white/[0.04] border border-white/10 p-[1.2vw]"><span className="text-fuchsia-300 font-semibold">When</span> referrer contains <span className="text-emerald-300">"instagram"</span> <span className="text-fuchsia-300 font-semibold">&rarr; go to</span> 1inme.com/ig-promo</div>
            <div className="rounded-lg bg-white/[0.04] border border-white/10 p-[1.2vw]"><span className="text-fuchsia-300 font-semibold">When</span> hour between <span className="text-emerald-300">"09:00"</span> &mdash; <span className="text-emerald-300">"17:00"</span> <span className="text-fuchsia-300 font-semibold">&rarr; go to</span> live.1inme.com</div>
            <div className="rounded-lg bg-violet-500/10 border border-violet-400/30 p-[1.2vw]"><span className="text-slate-400">Default</span> <span className="text-fuchsia-300 font-semibold">&rarr; go to</span> 1inme.com/everywhere</div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>22 / 84</span></div>
    </div>
  );
}
