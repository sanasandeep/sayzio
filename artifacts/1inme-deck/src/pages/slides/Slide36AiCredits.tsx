const base = import.meta.env.BASE_URL;

export default function Slide36AiCredits() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.2),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">AI · Credits</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">One credit balance, every AI surface.</h2>
        <p className="mt-[2vh] text-[1.4vw] text-slate-300 max-w-[55vw]">Companions, Minds, Coach, Voice, Scanner &mdash; all draw from the same pool. Top up once, use everywhere.</p>

        <div className="mt-[5vh] grid grid-cols-12 gap-[1.5vw]">
          <div className="col-span-5 rounded-2xl border border-white/10 bg-white/[0.04] p-[2vw]">
            <div className="text-[1vw] uppercase tracking-[0.25em] text-violet-300">Plan balance</div>
            <div className="mt-[1vh] font-display text-[5.5vw] font-bold bg-gradient-to-br from-violet-300 to-fuchsia-300 bg-clip-text text-transparent leading-none">12,400</div>
            <div className="mt-[1vh] text-[1.1vw] text-slate-300">credits available this month</div>
            <div className="mt-[2vh] h-[1vh] rounded-full bg-white/10"><div className="h-full w-[62%] rounded-full bg-gradient-to-r from-violet-400 to-fuchsia-400" /></div>
            <div className="mt-[1vh] text-[1vw] text-slate-400">62% of monthly allowance used</div>
          </div>
          <div className="col-span-7 grid grid-cols-2 gap-[1vw] content-start">
            <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.4vw]"><div className="text-[1vw] text-slate-400">Chat message</div><div className="font-display text-[1.6vw] font-semibold mt-[0.5vh]">1 credit</div></div>
            <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.4vw]"><div className="text-[1vw] text-slate-400">Mind reasoning step</div><div className="font-display text-[1.6vw] font-semibold mt-[0.5vh]">3 credits</div></div>
            <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.4vw]"><div className="text-[1vw] text-slate-400">Voice minute</div><div className="font-display text-[1.6vw] font-semibold mt-[0.5vh]">8 credits</div></div>
            <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.4vw]"><div className="text-[1vw] text-slate-400">Card scan</div><div className="font-display text-[1.6vw] font-semibold mt-[0.5vh]">2 credits</div></div>
            <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.4vw]"><div className="text-[1vw] text-slate-400">Image generation</div><div className="font-display text-[1.6vw] font-semibold mt-[0.5vh]">12 credits</div></div>
            <div className="rounded-xl border border-fuchsia-400/40 bg-fuchsia-500/10 p-[1.4vw]"><div className="text-[1vw] text-fuchsia-200">Coach review</div><div className="font-display text-[1.6vw] font-semibold mt-[0.5vh]">15 credits</div></div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>36 / 84</span></div>
    </div>
  );
}
