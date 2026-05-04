const base = import.meta.env.BASE_URL;

export default function Slide079Analyticsinsightsmockup() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400"></span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <div className="grid grid-cols-12 gap-[2.5vw] flex-1">
          <div className="col-span-5 flex flex-col justify-center">
            <h2 className="font-display text-[3.2vw] font-bold leading-[1.04] tracking-tight">Analytics &amp; Insights — in product.</h2>
            <p className="mt-[2vh] text-[1.25vw] text-slate-300 max-w-[26vw]">Editable mockup. Update copy and numbers in this slide file.</p>
            <ul className="mt-[3vh] space-y-[1vh] text-[1.05vw] text-slate-300"><li className="flex gap-[0.6vw]"><span className="text-fuchsia-300">&bull;</span><span>Replace these bullets with talking points</span></li><li className="flex gap-[0.6vw]"><span className="text-fuchsia-300">&bull;</span><span>Numbers below are placeholders</span></li><li className="flex gap-[0.6vw]"><span className="text-fuchsia-300">&bull;</span><span>Swap mock title for screenshot caption</span></li></ul>
          </div>
          <div className="col-span-7 rounded-2xl border border-white/10 bg-gradient-to-br from-white/[0.06] to-white/[0.02] p-[1.6vw] flex flex-col">
            <div className="flex items-center gap-[0.5vw] pb-[1vh] border-b border-white/10">
              <span className="h-[0.9vw] w-[0.9vw] rounded-full bg-rose-400/70" /><span className="h-[0.9vw] w-[0.9vw] rounded-full bg-amber-300/70" /><span className="h-[0.9vw] w-[0.9vw] rounded-full bg-emerald-400/70" />
              <span className="ml-[1vw] text-[0.9vw] text-slate-400 font-mono">Funnel · April</span>
            </div>
            <div className="mt-[1.5vh] flex flex-col gap-[0.7vh]">
            <div className="rounded-lg border border-white/10 bg-white/[0.03] px-[1vw] py-[0.8vh] flex items-center justify-between"><span className="text-[1vw] text-slate-200">Bio page views</span><span className="text-[0.9vw] text-fuchsia-200 font-mono">84,210</span></div>
            <div className="rounded-lg border border-white/10 bg-white/[0.03] px-[1vw] py-[0.8vh] flex items-center justify-between"><span className="text-[1vw] text-slate-200">Link clicks</span><span className="text-[0.9vw] text-fuchsia-200 font-mono">12,480</span></div>
            <div className="rounded-lg border border-white/10 bg-white/[0.03] px-[1vw] py-[0.8vh] flex items-center justify-between"><span className="text-[1vw] text-slate-200">Form submissions</span><span className="text-[0.9vw] text-fuchsia-200 font-mono">612</span></div>
            <div className="rounded-lg border border-white/10 bg-white/[0.03] px-[1vw] py-[0.8vh] flex items-center justify-between"><span className="text-[1vw] text-slate-200">Deals created</span><span className="text-[0.9vw] text-fuchsia-200 font-mono">47</span></div>
            </div>
            <div className="mt-auto pt-[1.5vh] text-[0.9vw] text-slate-500">Drill into any step to see who, where, and from which source.</div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>79 / 188</span></div>
    </div>
  );
}
