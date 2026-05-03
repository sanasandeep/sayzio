const base = import.meta.env.BASE_URL;

export default function Slide54Dialer() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_left,rgba(124,58,237,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Mobile · Smart Dialer</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-6 flex flex-col justify-center">
          <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight">Branded calls, with a brain on the other end.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[36vw] leading-snug">Show your brand on caller ID. Auto-log calls. Get an instant transcript and AI summary the moment you hang up.</p>
          <div className="mt-[3vh] flex flex-col gap-[1vh] text-[1.2vw]">
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Branded outbound caller ID</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Live transcription &amp; summary</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Auto-create CRM entry &amp; tasks</span></div>
          </div>
        </div>
        <div className="col-span-6 flex items-center justify-center">
          <div className="w-[26vw] aspect-[9/16] rounded-[3vw] border border-white/15 bg-[#0a0a14] p-[1.2vw] flex flex-col">
            <div className="flex-1 rounded-[2vw] bg-gradient-to-br from-violet-500/20 to-fuchsia-500/15 border border-white/10 p-[1.4vw] flex flex-col items-center justify-center">
              <div className="h-[10vh] w-[10vh] rounded-full bg-gradient-to-br from-violet-400 to-fuchsia-400" />
              <div className="mt-[2vh] font-display text-[1.8vw] font-semibold">Mira Okafor</div>
              <div className="text-[1vw] text-slate-300">Aurora Labs &middot; partnerships</div>
              <div className="mt-[3vh] text-[0.95vw] uppercase tracking-[0.3em] text-fuchsia-200">Calling via 1INME</div>
              <div className="mt-[2vh] text-[1.05vw] text-slate-300 text-center max-w-[80%]">Auto-summary, transcript, and tasks delivered after the call.</div>
            </div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>54 / 84</span></div>
    </div>
  );
}
