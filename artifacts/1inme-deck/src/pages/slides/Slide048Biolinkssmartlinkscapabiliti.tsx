const base = import.meta.env.BASE_URL;

export default function Slide048Biolinkssmartlinkscapabiliti() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="Sayzio" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400"></span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.04] tracking-tight max-w-[65vw]">Link in Bio &amp; Smart Links — key capabilities.</h2>
        
        <div className="mt-[4vh] grid grid-cols-4 gap-[1.4vw] flex-1 content-start">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="text-[0.85vw] uppercase tracking-[0.25em] text-fuchsia-200">Capability</div>
            <div className="font-display text-[1.5vw] font-semibold mt-[0.5vh]">Link in Bio pages</div>
            <div className="mt-[1vh] text-[1.05vw] text-slate-300 leading-snug">Drag-and-drop blocks, themes, custom domains.</div>
            
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="text-[0.85vw] uppercase tracking-[0.25em] text-fuchsia-200">Capability</div>
            <div className="font-display text-[1.5vw] font-semibold mt-[0.5vh]">Smart short links</div>
            <div className="mt-[1vh] text-[1.05vw] text-slate-300 leading-snug">Branded URLs with routing rules and pixels.</div>
            
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="text-[0.85vw] uppercase tracking-[0.25em] text-fuchsia-200">Capability</div>
            <div className="font-display text-[1.5vw] font-semibold mt-[0.5vh]">QR Studio</div>
            <div className="mt-[1vh] text-[1.05vw] text-slate-300 leading-snug">Logo-aware codes that update without reprint.</div>
            
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="text-[0.85vw] uppercase tracking-[0.25em] text-fuchsia-200">Capability</div>
            <div className="font-display text-[1.5vw] font-semibold mt-[0.5vh]">Splash screens</div>
            <div className="mt-[1vh] text-[1.05vw] text-slate-300 leading-snug">Branded interstitials between click and destination.</div>
            
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>49 / 189</span></div>
    </div>
  );
}
