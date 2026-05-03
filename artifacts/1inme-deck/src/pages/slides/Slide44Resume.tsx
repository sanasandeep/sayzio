const base = import.meta.env.BASE_URL;

export default function Slide44Resume() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_right,rgba(236,72,153,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Resume Builder</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-5 flex flex-col justify-center">
          <h2 className="font-display text-[3.4vw] font-bold leading-[1.02] tracking-tight">A resume that travels with your link.</h2>
          <p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[28vw] leading-snug">120+ templates, ATS-safe export, and a public version that lives at your-handle.com/resume.</p>
        </div>
        <div className="col-span-7 grid grid-cols-3 gap-[1vw] content-center">
          <div className="aspect-[3/4] rounded-xl bg-[#f8fafc] text-[#0a0a14] p-[1vw] flex flex-col"><div className="h-[1.5vh] w-[60%] bg-[#0a0a14] rounded" /><div className="mt-[0.6vh] h-[0.8vh] w-[40%] bg-slate-400 rounded" /><div className="mt-[1vh] h-[1px] w-full bg-slate-300" /><div className="mt-[1vh] h-[0.7vh] w-full bg-slate-300 rounded" /><div className="mt-[0.5vh] h-[0.7vh] w-[80%] bg-slate-300 rounded" /><div className="mt-[0.5vh] h-[0.7vh] w-[90%] bg-slate-300 rounded" /></div>
          <div className="aspect-[3/4] rounded-xl bg-[#1c0e2e] text-slate-100 p-[1vw] flex flex-col"><div className="h-[1.5vh] w-[55%] bg-fuchsia-300 rounded" /><div className="mt-[0.6vh] h-[0.8vh] w-[35%] bg-slate-400 rounded" /><div className="mt-[1vh] h-[1px] w-full bg-white/15" /><div className="mt-[1vh] h-[0.7vh] w-full bg-white/30 rounded" /><div className="mt-[0.5vh] h-[0.7vh] w-[80%] bg-white/30 rounded" /><div className="mt-[0.5vh] h-[0.7vh] w-[90%] bg-white/30 rounded" /></div>
          <div className="aspect-[3/4] rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-500 p-[1vw] flex flex-col"><div className="h-[1.5vh] w-[55%] bg-white/90 rounded" /><div className="mt-[0.6vh] h-[0.8vh] w-[35%] bg-white/60 rounded" /><div className="mt-[1vh] h-[1px] w-full bg-white/30" /><div className="mt-[1vh] h-[0.7vh] w-full bg-white/60 rounded" /><div className="mt-[0.5vh] h-[0.7vh] w-[80%] bg-white/60 rounded" /><div className="mt-[0.5vh] h-[0.7vh] w-[90%] bg-white/60 rounded" /></div>
          <div className="aspect-[3/4] rounded-xl bg-[#f5e9d3] text-[#3a2a1a] p-[1vw] flex flex-col"><div className="h-[1.5vh] w-[60%] bg-[#3a2a1a] rounded" /><div className="mt-[0.6vh] h-[0.8vh] w-[40%] bg-[#7d6a5a] rounded" /><div className="mt-[1vh] h-[1px] w-full bg-[#3a2a1a]/30" /><div className="mt-[1vh] h-[0.7vh] w-full bg-[#7d6a5a] rounded" /><div className="mt-[0.5vh] h-[0.7vh] w-[80%] bg-[#7d6a5a] rounded" /><div className="mt-[0.5vh] h-[0.7vh] w-[90%] bg-[#7d6a5a] rounded" /></div>
          <div className="aspect-[3/4] rounded-xl bg-[#0e0e1a] border border-white/10 p-[1vw] flex flex-col"><div className="h-[1.5vh] w-[55%] bg-cyan-300 rounded" /><div className="mt-[0.6vh] h-[0.8vh] w-[35%] bg-slate-400 rounded" /><div className="mt-[1vh] h-[1px] w-full bg-white/15" /><div className="mt-[1vh] h-[0.7vh] w-full bg-white/30 rounded" /><div className="mt-[0.5vh] h-[0.7vh] w-[80%] bg-white/30 rounded" /><div className="mt-[0.5vh] h-[0.7vh] w-[90%] bg-white/30 rounded" /></div>
          <div className="aspect-[3/4] rounded-xl border border-fuchsia-400/30 bg-fuchsia-500/10 p-[1vw] flex items-center justify-center text-[1.1vw] font-semibold text-fuchsia-200">+ 115 more</div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>44 / 84</span></div>
    </div>
  );
}
