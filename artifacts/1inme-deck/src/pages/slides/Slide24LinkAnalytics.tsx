const base = import.meta.env.BASE_URL;

export default function Slide24LinkAnalytics() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.2),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Link analytics</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">Every click, mapped and explained.</h2>

        <div className="mt-[5vh] grid grid-cols-12 gap-[1.5vw]">
          <div className="col-span-3 rounded-2xl border border-white/10 bg-white/[0.04] p-[1.5vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-violet-300">Clicks · 30d</div><div className="font-display text-[3.4vw] font-bold mt-[0.8vh]">128.4k</div><div className="text-[1vw] text-emerald-300 mt-[0.5vh]">&uarr; 22% vs prior</div></div>
          <div className="col-span-3 rounded-2xl border border-white/10 bg-white/[0.04] p-[1.5vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-fuchsia-300">Unique visitors</div><div className="font-display text-[3.4vw] font-bold mt-[0.8vh]">73.1k</div><div className="text-[1vw] text-emerald-300 mt-[0.5vh]">&uarr; 18%</div></div>
          <div className="col-span-3 rounded-2xl border border-white/10 bg-white/[0.04] p-[1.5vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-cyan-300">Top country</div><div className="font-display text-[2.6vw] font-bold mt-[0.8vh]">United States</div><div className="text-[1vw] text-slate-400 mt-[0.5vh]">41.2% of clicks</div></div>
          <div className="col-span-3 rounded-2xl border border-white/10 bg-white/[0.04] p-[1.5vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-violet-300">Top device</div><div className="font-display text-[2.6vw] font-bold mt-[0.8vh]">iOS Mobile</div><div className="text-[1vw] text-slate-400 mt-[0.5vh]">62% of sessions</div></div>

          <div className="col-span-12 rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]">
            <div className="flex items-center justify-between"><div className="text-[1.1vw] text-slate-300">Clicks over time</div><div className="text-[1vw] text-slate-500">Last 30 days</div></div>
            <div className="mt-[2vh] h-[18vh] flex items-end gap-[0.4vw]">
              <div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'30%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'42%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'38%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'55%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'48%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'66%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'58%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'72%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'80%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'68%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'76%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'88%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'82%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'95%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'78%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'90%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'72%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'85%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'92%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'100%'}} />
            </div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>24 / 84</span></div>
    </div>
  );
}
