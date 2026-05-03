export default function Slide28AiCompanions() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(124,58,237,0.22),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">AI · Companions</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-6 flex flex-col justify-center">
          <span className="text-[1vw] uppercase tracking-[0.3em] text-violet-300">AI Companions</span>
          <h2 className="mt-[1.5vh] font-display text-[3.8vw] font-bold leading-[1.02] tracking-tight">Characters with personality, memory, and presence.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[36vw] leading-snug">Build conversational characters for your audience &mdash; mentors, sparring partners, fan favourites &mdash; embedded directly in your biolink.</p>
        </div>
        <div className="col-span-6 grid grid-cols-2 gap-[1.5vw] content-center">
          <div className="rounded-2xl border border-violet-400/30 bg-gradient-to-br from-violet-500/15 to-transparent p-[1.6vw]"><div className="h-[3vw] w-[3vw] rounded-full bg-gradient-to-br from-violet-400 to-fuchsia-400" /><div className="mt-[1vh] font-display text-[1.6vw] font-semibold">Mentor</div><div className="text-[1.05vw] text-slate-300">Wise, patient, asks the next question.</div></div>
          <div className="rounded-2xl border border-fuchsia-400/30 bg-gradient-to-br from-fuchsia-500/15 to-transparent p-[1.6vw]"><div className="h-[3vw] w-[3vw] rounded-full bg-gradient-to-br from-fuchsia-400 to-rose-400" /><div className="mt-[1vh] font-display text-[1.6vw] font-semibold">Hype</div><div className="text-[1.05vw] text-slate-300">Bold, energetic, ships your work.</div></div>
          <div className="rounded-2xl border border-cyan-400/30 bg-gradient-to-br from-cyan-500/15 to-transparent p-[1.6vw]"><div className="h-[3vw] w-[3vw] rounded-full bg-gradient-to-br from-cyan-400 to-emerald-300" /><div className="mt-[1vh] font-display text-[1.6vw] font-semibold">Analyst</div><div className="text-[1.05vw] text-slate-300">Calm, structured, shows the data.</div></div>
          <div className="rounded-2xl border border-amber-300/30 bg-gradient-to-br from-amber-400/15 to-transparent p-[1.6vw]"><div className="h-[3vw] w-[3vw] rounded-full bg-gradient-to-br from-amber-400 to-rose-400" /><div className="mt-[1vh] font-display text-[1.6vw] font-semibold">Custom</div><div className="text-[1.05vw] text-slate-300">Voice, tone, and persona &mdash; yours.</div></div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>28 / 84</span></div>
    </div>
  );
}
