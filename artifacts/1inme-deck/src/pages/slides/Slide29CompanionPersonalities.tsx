const base = import.meta.env.BASE_URL;

export default function Slide29CompanionPersonalities() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_right,rgba(236,72,153,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">AI · Personality &amp; Voice</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">Tune the personality, not just the prompt.</h2>

        <div className="mt-[5vh] grid grid-cols-12 gap-[2vw]">
          <div className="col-span-7 rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]">
            <div className="text-[1vw] uppercase tracking-[0.25em] text-violet-300">Voice profile</div>
            <div className="mt-[2vh] flex flex-col gap-[1.4vh]">
              <div><div className="flex justify-between text-[1vw] text-slate-300"><span>Warmth</span><span>72%</span></div><div className="mt-[0.5vh] h-[0.8vh] rounded-full bg-white/10"><div className="h-full rounded-full bg-gradient-to-r from-violet-400 to-fuchsia-400" style={{width:'72%'}} /></div></div>
              <div><div className="flex justify-between text-[1vw] text-slate-300"><span>Formality</span><span>40%</span></div><div className="mt-[0.5vh] h-[0.8vh] rounded-full bg-white/10"><div className="h-full rounded-full bg-gradient-to-r from-violet-400 to-fuchsia-400" style={{width:'40%'}} /></div></div>
              <div><div className="flex justify-between text-[1vw] text-slate-300"><span>Humour</span><span>58%</span></div><div className="mt-[0.5vh] h-[0.8vh] rounded-full bg-white/10"><div className="h-full rounded-full bg-gradient-to-r from-violet-400 to-fuchsia-400" style={{width:'58%'}} /></div></div>
              <div><div className="flex justify-between text-[1vw] text-slate-300"><span>Detail</span><span>85%</span></div><div className="mt-[0.5vh] h-[0.8vh] rounded-full bg-white/10"><div className="h-full rounded-full bg-gradient-to-r from-violet-400 to-fuchsia-400" style={{width:'85%'}} /></div></div>
            </div>
          </div>
          <div className="col-span-5 rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw] flex flex-col gap-[1.2vh]">
            <div className="text-[1vw] uppercase tracking-[0.25em] text-fuchsia-300">Capabilities</div>
            <div className="text-[1.2vw]">Long-term memory of your audience</div>
            <div className="text-[1.2vw]">Voice replies in 18 languages</div>
            <div className="text-[1.2vw]">Knowledge from your Mind</div>
            <div className="text-[1.2vw]">Booking, payment, and link handoff</div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>29 / 84</span></div>
    </div>
  );
}
