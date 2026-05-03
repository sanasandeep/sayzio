export default function Slide41TaskBoard() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Task Board</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">A board you actually finish.</h2>
        <p className="mt-[1.5vh] text-[1.3vw] text-slate-300 max-w-[55vw]">Kanban, list, and timeline views. Assignees, due dates, dependencies, and AI breakdowns.</p>

        <div className="mt-[4vh] grid grid-cols-4 gap-[1.2vw] flex-1">
          <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-[1.2vw] flex flex-col gap-[0.8vh]"><div className="text-[0.95vw] uppercase tracking-[0.2em] text-slate-400">Backlog &middot; 12</div><div className="rounded-lg bg-white/[0.05] p-[1vw] text-[1.05vw]">Refresh pricing page</div><div className="rounded-lg bg-white/[0.05] p-[1vw] text-[1.05vw]">Audit onboarding emails</div><div className="rounded-lg bg-white/[0.05] p-[1vw] text-[1.05vw]">Plan Q3 launch</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-[1.2vw] flex flex-col gap-[0.8vh]"><div className="text-[0.95vw] uppercase tracking-[0.2em] text-violet-300">Today &middot; 4</div><div className="rounded-lg bg-violet-500/15 border border-violet-400/30 p-[1vw] text-[1.05vw]">Reply to Aurora deal</div><div className="rounded-lg bg-violet-500/15 border border-violet-400/30 p-[1vw] text-[1.05vw]">Draft May newsletter</div><div className="rounded-lg bg-violet-500/15 border border-violet-400/30 p-[1vw] text-[1.05vw]">Approve scanner copy</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-[1.2vw] flex flex-col gap-[0.8vh]"><div className="text-[0.95vw] uppercase tracking-[0.2em] text-fuchsia-300">Doing &middot; 2</div><div className="rounded-lg bg-fuchsia-500/15 border border-fuchsia-400/30 p-[1vw] text-[1.05vw]">Re-record voice demo</div><div className="rounded-lg bg-fuchsia-500/15 border border-fuchsia-400/30 p-[1vw] text-[1.05vw]">QA new themes</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-[1.2vw] flex flex-col gap-[0.8vh]"><div className="text-[0.95vw] uppercase tracking-[0.2em] text-emerald-300">Done &middot; 7</div><div className="rounded-lg bg-emerald-500/10 border border-emerald-400/20 p-[1vw] text-[1.05vw] line-through text-slate-400">Ship card scanner</div><div className="rounded-lg bg-emerald-500/10 border border-emerald-400/20 p-[1vw] text-[1.05vw] line-through text-slate-400">Splash screen v2</div><div className="rounded-lg bg-emerald-500/10 border border-emerald-400/20 p-[1vw] text-[1.05vw] line-through text-slate-400">SOC2 evidence pack</div></div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>41 / 84</span></div>
    </div>
  );
}
