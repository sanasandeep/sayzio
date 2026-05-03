export default function Slide32AiCoach() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.2),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">AI · Coach</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-6 flex flex-col justify-center">
          <span className="text-[1vw] uppercase tracking-[0.3em] text-fuchsia-300">AI Coach</span>
          <h2 className="mt-[1.5vh] font-display text-[3.8vw] font-bold leading-[1.02] tracking-tight">Accountability that actually shows up.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[36vw] leading-snug">A daily check-in built around your goals. The Coach reads your week, asks the right question, and helps you decide what&rsquo;s next.</p>
        </div>
        <div className="col-span-6 flex items-center">
          <div className="w-full rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw] flex flex-col gap-[1.4vh]">
            <div className="flex items-start gap-[1vw]"><div className="h-[2.4vw] w-[2.4vw] rounded-full bg-gradient-to-br from-violet-400 to-fuchsia-400" /><div className="flex-1 rounded-xl bg-white/[0.04] p-[1.2vw] text-[1.15vw] text-slate-200">Yesterday you closed 3 tasks and skipped your writing block. Want to talk about why?</div></div>
            <div className="flex items-start gap-[1vw] flex-row-reverse"><div className="h-[2.4vw] w-[2.4vw] rounded-full bg-white/10" /><div className="flex-1 rounded-xl bg-violet-500/15 border border-violet-400/30 p-[1.2vw] text-[1.15vw] text-slate-100">Got pulled into client calls all day.</div></div>
            <div className="flex items-start gap-[1vw]"><div className="h-[2.4vw] w-[2.4vw] rounded-full bg-gradient-to-br from-violet-400 to-fuchsia-400" /><div className="flex-1 rounded-xl bg-white/[0.04] p-[1.2vw] text-[1.15vw] text-slate-200">I&rsquo;ll move tomorrow&rsquo;s 9am block to a hard-block. Sound good?</div></div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>32 / 84</span></div>
    </div>
  );
}
