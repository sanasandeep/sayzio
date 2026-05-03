const base = import.meta.env.BASE_URL;

export default function Slide51Referral() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(236,72,153,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Referral Program</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-6 flex flex-col justify-center">
          <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight">A growth loop, not a gimmick.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[36vw] leading-snug">Every member gets a referral link. Both sides earn credits, payouts, or trial extensions &mdash; you choose.</p>
          <div className="mt-[3vh] flex flex-col gap-[1vh] text-[1.2vw]">
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Stripe Connect payouts in 30+ countries</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Tiered rewards &amp; lifetime tracking</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Embed referral widget anywhere</span></div>
          </div>
        </div>
        <div className="col-span-6 flex items-center">
          <div className="w-full grid grid-cols-2 gap-[1.5vw]">
            <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-violet-300">Referrals</div><div className="font-display text-[3.6vw] font-bold mt-[0.5vh] leading-none">218</div><div className="text-[1vw] text-slate-400 mt-[0.5vh]">last 90 days</div></div>
            <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-fuchsia-300">Conversions</div><div className="font-display text-[3.6vw] font-bold mt-[0.5vh] leading-none">61</div><div className="text-[1vw] text-slate-400 mt-[0.5vh]">28% rate</div></div>
            <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-cyan-300">Payouts</div><div className="font-display text-[3.6vw] font-bold mt-[0.5vh] leading-none">$2.4k</div><div className="text-[1vw] text-slate-400 mt-[0.5vh]">paid out</div></div>
            <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-emerald-300">Top advocate</div><div className="font-display text-[1.8vw] font-semibold mt-[0.5vh]">@northwind</div><div className="text-[1vw] text-slate-400 mt-[0.5vh]">38 paid referrals</div></div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>51 / 84</span></div>
    </div>
  );
}
