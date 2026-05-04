const base = import.meta.env.BASE_URL;

export default function Slide100Creatorsmusiciansday() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">A day in the life</span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.04] tracking-tight">A day in the life — musicians.</h2>
        <p className="mt-[2vh] text-[1.25vw] text-slate-300 max-w-[65vw]">Time, module, action — all happening inside 1INME.</p>
        <div className="mt-[4vh] flex-1 flex flex-col gap-[1.6vh]">
          <div className="grid grid-cols-12 gap-[1.5vw] items-start">
            <div className="col-span-2 font-display text-[1.6vw] font-bold text-fuchsia-200">9:00</div>
            <div className="col-span-3 text-[1.05vw] text-slate-400">Vault</div>
            <div className="col-span-7 text-[1.1vw] text-slate-200 leading-snug">Shares the EPK with a new venue.</div>
          </div>
          <div className="grid grid-cols-12 gap-[1.5vw] items-start">
            <div className="col-span-2 font-display text-[1.6vw] font-bold text-fuchsia-200">12:00</div>
            <div className="col-span-3 text-[1.05vw] text-slate-400">Calendar</div>
            <div className="col-span-7 text-[1.1vw] text-slate-200 leading-snug">Confirms a tour date and updates the bio block.</div>
          </div>
          <div className="grid grid-cols-12 gap-[1.5vw] items-start">
            <div className="col-span-2 font-display text-[1.6vw] font-bold text-fuchsia-200">15:00</div>
            <div className="col-span-3 text-[1.05vw] text-slate-400">Companion</div>
            <div className="col-span-7 text-[1.1vw] text-slate-200 leading-snug">Generates city-specific announcement copy.</div>
          </div>
          <div className="grid grid-cols-12 gap-[1.5vw] items-start">
            <div className="col-span-2 font-display text-[1.6vw] font-bold text-fuchsia-200">19:00</div>
            <div className="col-span-3 text-[1.05vw] text-slate-400">Smart Links</div>
            <div className="col-span-7 text-[1.1vw] text-slate-200 leading-snug">Routes US fans to Apple Music, EU to Spotify.</div>
          </div>
          <div className="grid grid-cols-12 gap-[1.5vw] items-start">
            <div className="col-span-2 font-display text-[1.6vw] font-bold text-fuchsia-200">23:00</div>
            <div className="col-span-3 text-[1.05vw] text-slate-400">Analytics</div>
            <div className="col-span-7 text-[1.1vw] text-slate-200 leading-snug">Sees stream spikes by region.</div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>100 / 188</span></div>
    </div>
  );
}
