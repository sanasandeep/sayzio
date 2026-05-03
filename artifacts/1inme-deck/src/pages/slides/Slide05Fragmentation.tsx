const base = import.meta.env.BASE_URL;

export default function Slide05Fragmentation() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(236,72,153,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">The problem</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[14vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[4.2vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">The average creator pays for nine subscriptions.</h2>
        <p className="mt-[2vh] text-[1.4vw] text-slate-300 max-w-[55vw]">Most are 80% redundant. None of them talk to each other.</p>

        <div className="mt-[5vh] grid grid-cols-4 gap-[1.5vw]">
          <div className="rounded-xl border border-white/10 bg-white/[0.03] p-[1.6vw]"><div className="font-display text-[2.6vw] font-bold text-violet-300">9</div><div className="mt-[0.5vh] text-[1.1vw] text-slate-300">SaaS tools per active creator</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.03] p-[1.6vw]"><div className="font-display text-[2.6vw] font-bold text-violet-300">$214</div><div className="mt-[0.5vh] text-[1.1vw] text-slate-300">Average monthly stack cost</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.03] p-[1.6vw]"><div className="font-display text-[2.6vw] font-bold text-violet-300">37%</div><div className="mt-[0.5vh] text-[1.1vw] text-slate-300">Of features ever used</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.03] p-[1.6vw]"><div className="font-display text-[2.6vw] font-bold text-violet-300">2.4h</div><div className="mt-[0.5vh] text-[1.1vw] text-slate-300">Daily context switching</div></div>
        </div>

        <p className="mt-[5vh] text-[1.3vw] text-slate-400 max-w-[60vw]">Figures based on internal 1INME user research. Independent stacks vary by role.</p>
      </div>

      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>05 / 84</span></div>
    </div>
  );
}
