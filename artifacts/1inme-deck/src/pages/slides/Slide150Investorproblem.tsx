const base = import.meta.env.BASE_URL;

export default function Slide150Investorproblem() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="Sayzio" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400"></span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.6vw] font-bold leading-[1.04] tracking-tight max-w-[65vw]">Problem.</h2>
        
        <div className="mt-[5vh] grid grid-cols-4 gap-[1.5vw]">
          <div className="rounded-xl border border-white/10 bg-white/[0.03] p-[1.6vw]"><div className="font-display text-[2.8vw] font-bold text-blue-300">9</div><div className="mt-[0.5vh] text-[1.05vw] text-slate-300">tools per active creator</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.03] p-[1.6vw]"><div className="font-display text-[2.8vw] font-bold text-blue-300">$214</div><div className="mt-[0.5vh] text-[1.05vw] text-slate-300">stack cost / month</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.03] p-[1.6vw]"><div className="font-display text-[2.8vw] font-bold text-blue-300">2.4h</div><div className="mt-[0.5vh] text-[1.05vw] text-slate-300">lost daily to context switching</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.03] p-[1.6vw]"><div className="font-display text-[2.8vw] font-bold text-blue-300">37%</div><div className="mt-[0.5vh] text-[1.05vw] text-slate-300">of features ever used</div></div>
        </div>
        <p className="mt-[4vh] text-[1vw] text-slate-500 max-w-[60vw]">Placeholder market figures. Swap for verified secondary research before sending.</p>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>151 / 189</span></div>
    </div>
  );
}
