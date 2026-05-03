export default function Slide12WebApp() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(124,58,237,0.22),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Platforms · Web</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-7 flex flex-col justify-center">
          <span className="text-[1vw] uppercase tracking-[0.3em] text-violet-300">The Web app</span>
          <h2 className="mt-[1.5vh] font-display text-[4vw] font-bold leading-[1.02] tracking-tight">A workspace fast enough to live in.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[42vw] leading-snug">Server-rendered Laravel core, Livewire reactivity, instant navigation, and a design system tuned for hours-on-end use.</p>
          <div className="mt-[3vh] grid grid-cols-2 gap-[1vw] max-w-[42vw]">
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300 text-[1.2vw]">&rarr;</span><span className="text-[1.2vw]">Sub-second navigation</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300 text-[1.2vw]">&rarr;</span><span className="text-[1.2vw]">Light, dark, and system themes</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300 text-[1.2vw]">&rarr;</span><span className="text-[1.2vw]">Keyboard-first command bar</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300 text-[1.2vw]">&rarr;</span><span className="text-[1.2vw]">Fully responsive down to 360px</span></div>
          </div>
        </div>
        <div className="col-span-5 flex items-center justify-center">
          <div className="w-full h-[60vh] rounded-2xl border border-white/10 bg-gradient-to-br from-violet-500/15 via-white/[0.03] to-fuchsia-500/10 p-[1.2vw] flex flex-col">
            <div className="flex items-center gap-[0.6vw] pb-[1.5vh]"><div className="h-[0.8vw] w-[0.8vw] rounded-full bg-rose-400/70" /><div className="h-[0.8vw] w-[0.8vw] rounded-full bg-amber-300/70" /><div className="h-[0.8vw] w-[0.8vw] rounded-full bg-emerald-400/70" /><div className="ml-auto text-[0.8vw] text-slate-400">app.1inme.com</div></div>
            <div className="flex-1 rounded-xl bg-[#0a0a14] border border-white/10 grid grid-cols-12 gap-[0.6vw] p-[1vw]">
              <div className="col-span-3 rounded-lg bg-white/[0.04] p-[0.8vw] flex flex-col gap-[0.7vh]"><div className="h-[1.4vh] w-[80%] rounded bg-violet-400/40" /><div className="h-[1vh] w-[60%] rounded bg-white/10" /><div className="h-[1vh] w-[70%] rounded bg-white/10" /><div className="h-[1vh] w-[50%] rounded bg-white/10" /><div className="h-[1vh] w-[65%] rounded bg-white/10" /></div>
              <div className="col-span-9 rounded-lg bg-white/[0.04] p-[0.8vw] flex flex-col gap-[1vh]"><div className="h-[2vh] w-[40%] rounded bg-fuchsia-400/40" /><div className="grid grid-cols-3 gap-[0.6vw]"><div className="h-[10vh] rounded bg-white/[0.06]" /><div className="h-[10vh] rounded bg-white/[0.06]" /><div className="h-[10vh] rounded bg-white/[0.06]" /></div><div className="h-[12vh] rounded bg-gradient-to-br from-violet-500/30 to-fuchsia-500/20" /></div>
            </div>
          </div>
        </div>
      </div>

      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>12 / 84</span></div>
    </div>
  );
}
