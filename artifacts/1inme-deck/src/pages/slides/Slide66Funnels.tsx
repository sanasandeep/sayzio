export default function Slide66Funnels() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(124,58,237,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Conversion funnels</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-5 flex flex-col justify-center">
          <h2 className="font-display text-[3.4vw] font-bold leading-[1.02] tracking-tight">Watch a visit become a customer.</h2>
          <p className="mt-[2.5vh] text-[1.3vw] text-slate-300 max-w-[28vw] leading-snug">Build funnels across biolinks, forms, bookings, and checkout. See exactly where people drop.</p>
        </div>
        <div className="col-span-7 flex items-center">
          <div className="w-full rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw] flex flex-col gap-[1.4vh]">
            <div className="rounded-xl bg-violet-500/15 border border-violet-400/30 p-[1.4vw]"><div className="flex justify-between items-center"><div className="font-display text-[1.4vw] font-semibold">Visited biolink</div><div className="font-display text-[2vw] font-bold">12,400</div></div><div className="mt-[0.6vh] h-[1vh] rounded-full bg-white/10"><div className="h-full bg-violet-400 rounded-full" style={{width:'100%'}} /></div></div>
            <div className="rounded-xl bg-fuchsia-500/15 border border-fuchsia-400/30 p-[1.4vw] mx-[3vw]"><div className="flex justify-between items-center"><div className="font-display text-[1.4vw] font-semibold">Opened booking</div><div className="font-display text-[2vw] font-bold">4,720</div></div><div className="mt-[0.6vh] h-[1vh] rounded-full bg-white/10"><div className="h-full bg-fuchsia-400 rounded-full" style={{width:'70%'}} /></div></div>
            <div className="rounded-xl bg-rose-500/15 border border-rose-400/30 p-[1.4vw] mx-[6vw]"><div className="flex justify-between items-center"><div className="font-display text-[1.4vw] font-semibold">Selected slot</div><div className="font-display text-[2vw] font-bold">1,840</div></div><div className="mt-[0.6vh] h-[1vh] rounded-full bg-white/10"><div className="h-full bg-rose-400 rounded-full" style={{width:'45%'}} /></div></div>
            <div className="rounded-xl bg-emerald-500/15 border border-emerald-400/30 p-[1.4vw] mx-[9vw]"><div className="flex justify-between items-center"><div className="font-display text-[1.4vw] font-semibold">Confirmed</div><div className="font-display text-[2vw] font-bold">912</div></div><div className="mt-[0.6vh] h-[1vh] rounded-full bg-white/10"><div className="h-full bg-emerald-400 rounded-full" style={{width:'25%'}} /></div></div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>66 / 84</span></div>
    </div>
  );
}
