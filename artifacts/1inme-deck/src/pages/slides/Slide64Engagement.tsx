export default function Slide64Engagement() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.2),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Engagement analytics</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">What people did, not just how many.</h2>

        <div className="mt-[4vh] grid grid-cols-12 gap-[1.5vw] flex-1">
          <div className="col-span-7 rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw] flex flex-col">
            <div className="flex items-center justify-between"><div className="font-display text-[1.4vw] font-semibold">Sessions by day</div><div className="text-[0.95vw] text-slate-400">Last 14 days</div></div>
            <div className="mt-[2vh] flex-1 flex items-end gap-[0.5vw]">
              <div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'30%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'48%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'42%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'58%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'52%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'70%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'62%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'76%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'68%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'82%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'74%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'90%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'85%'}} /><div className="w-full rounded-t bg-gradient-to-t from-violet-500/30 to-fuchsia-400" style={{height:'100%'}} />
            </div>
          </div>
          <div className="col-span-5 grid grid-cols-1 gap-[1.5vw] content-stretch">
            <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-violet-300">Avg session</div><div className="font-display text-[2.6vw] font-bold mt-[0.5vh] leading-none">1m 48s</div></div>
            <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-fuchsia-300">Top block</div><div className="font-display text-[2.2vw] font-bold mt-[0.5vh] leading-none">Booking link</div><div className="text-[1vw] text-slate-400 mt-[0.5vh]">42% of clicks</div></div>
            <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-cyan-300">New followers</div><div className="font-display text-[2.6vw] font-bold mt-[0.5vh] leading-none">+412</div></div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>64 / 84</span></div>
    </div>
  );
}
