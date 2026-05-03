export default function Slide62Affiliate() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Affiliate payouts</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-6 flex flex-col justify-center">
          <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight">A real revenue share for the people who tell our story.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[36vw] leading-snug">30% recurring on every paid plan, 90-day cookie, monthly Stripe payouts in your local currency.</p>
        </div>
        <div className="col-span-6 grid grid-cols-2 gap-[1.5vw] content-center">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-violet-300">Commission</div><div className="font-display text-[3.4vw] font-bold mt-[0.5vh] leading-none">30%</div><div className="text-[1vw] text-slate-300 mt-[0.5vh]">recurring &middot; for life</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-fuchsia-300">Cookie</div><div className="font-display text-[3.4vw] font-bold mt-[0.5vh] leading-none">90 days</div><div className="text-[1vw] text-slate-300 mt-[0.5vh]">attribution window</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-cyan-300">Payouts</div><div className="font-display text-[3.4vw] font-bold mt-[0.5vh] leading-none">Monthly</div><div className="text-[1vw] text-slate-300 mt-[0.5vh]">via Stripe Connect</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-emerald-300">Min payout</div><div className="font-display text-[3.4vw] font-bold mt-[0.5vh] leading-none">$25</div><div className="text-[1vw] text-slate-300 mt-[0.5vh]">in 30+ countries</div></div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>62 / 84</span></div>
    </div>
  );
}
