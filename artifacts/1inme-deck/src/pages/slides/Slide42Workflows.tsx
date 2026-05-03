export default function Slide42Workflows() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_right,rgba(236,72,153,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Workflows &amp; automations</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-5 flex flex-col justify-center">
          <h2 className="font-display text-[3.4vw] font-bold leading-[1.02] tracking-tight">If-this-then-that, with a brain.</h2>
          <p className="mt-[2.5vh] text-[1.3vw] text-slate-300 max-w-[28vw] leading-snug">Trigger workflows from any event in 1INME &mdash; click, scan, form, booking &mdash; and chain AI steps in between.</p>
        </div>
        <div className="col-span-7 flex items-center">
          <div className="w-full grid grid-cols-7 gap-[0.8vw] items-center text-[1.05vw]">
            <div className="col-span-2 rounded-xl border border-violet-400/30 bg-violet-500/10 p-[1.2vw] text-center font-display font-semibold">Form submitted</div>
            <div className="col-span-1 text-center text-slate-500 text-[1.5vw]">&rarr;</div>
            <div className="col-span-2 rounded-xl border border-fuchsia-400/30 bg-fuchsia-500/10 p-[1.2vw] text-center font-display font-semibold">AI enrich lead</div>
            <div className="col-span-1 text-center text-slate-500 text-[1.5vw]">&rarr;</div>
            <div className="col-span-1 rounded-xl border border-cyan-400/30 bg-cyan-500/10 p-[1.2vw] text-center font-display font-semibold">CRM</div>

            <div className="col-span-2 rounded-xl border border-violet-400/30 bg-violet-500/10 p-[1.2vw] text-center font-display font-semibold">Card scanned</div>
            <div className="col-span-1 text-center text-slate-500 text-[1.5vw]">&rarr;</div>
            <div className="col-span-2 rounded-xl border border-fuchsia-400/30 bg-fuchsia-500/10 p-[1.2vw] text-center font-display font-semibold">Draft follow-up</div>
            <div className="col-span-1 text-center text-slate-500 text-[1.5vw]">&rarr;</div>
            <div className="col-span-1 rounded-xl border border-cyan-400/30 bg-cyan-500/10 p-[1.2vw] text-center font-display font-semibold">Inbox</div>

            <div className="col-span-2 rounded-xl border border-violet-400/30 bg-violet-500/10 p-[1.2vw] text-center font-display font-semibold">Booking made</div>
            <div className="col-span-1 text-center text-slate-500 text-[1.5vw]">&rarr;</div>
            <div className="col-span-2 rounded-xl border border-fuchsia-400/30 bg-fuchsia-500/10 p-[1.2vw] text-center font-display font-semibold">Coach prep brief</div>
            <div className="col-span-1 text-center text-slate-500 text-[1.5vw]">&rarr;</div>
            <div className="col-span-1 rounded-xl border border-cyan-400/30 bg-cyan-500/10 p-[1.2vw] text-center font-display font-semibold">Tasks</div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>42 / 84</span></div>
    </div>
  );
}
