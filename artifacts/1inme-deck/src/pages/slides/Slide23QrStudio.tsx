const base = import.meta.env.BASE_URL;

export default function Slide23QrStudio() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(236,72,153,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">QR Studio</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-6 flex flex-col justify-center">
          <h2 className="font-display text-[3.8vw] font-bold leading-[1.02] tracking-tight">QR codes that look like a brand asset.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[36vw] leading-snug">Custom shapes, gradients, embedded logos, error-correction tuned for print, and dynamic destinations behind every scan.</p>
          <div className="mt-[3vh] flex flex-col gap-[1vh] text-[1.2vw]">
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>SVG, PNG, and PDF export at any size</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Change destination without reprinting</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Per-scan analytics, geo &amp; device</span></div>
          </div>
        </div>
        <div className="col-span-6 flex items-center justify-center">
          <div className="grid grid-cols-2 gap-[1.5vw]">
            <div className="h-[24vh] w-[24vh] rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-500 grid place-items-center"><div className="h-[18vh] w-[18vh] bg-[#0a0a14] rounded-xl grid grid-cols-7 grid-rows-7 gap-[0.4vh] p-[1.2vh]"><div className="bg-white rounded-sm" /><div /><div className="bg-white rounded-sm" /><div className="bg-white rounded-sm" /><div /><div className="bg-white rounded-sm" /><div className="bg-white rounded-sm" /><div /><div className="bg-white rounded-sm" /><div /><div className="bg-white rounded-sm" /><div className="bg-white rounded-sm" /><div /><div className="bg-white rounded-sm" /><div className="bg-white rounded-sm" /><div /><div className="bg-white rounded-sm" /><div className="bg-white rounded-sm" /><div className="bg-white rounded-sm" /><div /><div className="bg-white rounded-sm" /><div /><div className="bg-white rounded-sm" /><div /><div className="bg-white rounded-sm" /><div className="bg-white rounded-sm" /><div /><div className="bg-white rounded-sm" /><div className="bg-white rounded-sm" /><div /><div className="bg-white rounded-sm" /><div className="bg-white rounded-sm" /><div /><div className="bg-white rounded-sm" /><div className="bg-white rounded-sm" /><div /><div className="bg-white rounded-sm" /><div /><div className="bg-white rounded-sm" /><div className="bg-white rounded-sm" /><div /><div className="bg-white rounded-sm" /><div className="bg-white rounded-sm" /><div /><div className="bg-white rounded-sm" /><div className="bg-white rounded-sm" /><div /><div className="bg-white rounded-sm" /><div /><div className="bg-white rounded-sm" /></div></div>
            <div className="h-[24vh] w-[24vh] rounded-2xl bg-[#f5e9d3] grid place-items-center"><div className="h-[18vh] w-[18vh] bg-[#3a2a1a] rounded-full grid place-items-center"><div className="h-[6vh] w-[6vh] rounded-full bg-[#f5e9d3]" /></div></div>
            <div className="h-[24vh] w-[24vh] rounded-2xl bg-[#0e0e1a] border border-white/10 grid place-items-center"><div className="h-[18vh] w-[18vh] grid grid-cols-5 grid-rows-5 gap-[0.5vh]"><div className="bg-cyan-300 rounded-sm" /><div /><div className="bg-cyan-300 rounded-sm" /><div className="bg-cyan-300 rounded-sm" /><div /><div className="bg-cyan-300 rounded-sm" /><div className="bg-cyan-300 rounded-sm" /><div /><div className="bg-cyan-300 rounded-sm" /><div className="bg-cyan-300 rounded-sm" /><div /><div className="bg-cyan-300 rounded-sm" /><div className="bg-cyan-300 rounded-sm" /><div /><div className="bg-cyan-300 rounded-sm" /><div className="bg-cyan-300 rounded-sm" /><div /><div className="bg-cyan-300 rounded-sm" /><div className="bg-cyan-300 rounded-sm" /><div /><div /><div className="bg-cyan-300 rounded-sm" /><div /><div className="bg-cyan-300 rounded-sm" /><div className="bg-cyan-300 rounded-sm" /></div></div>
            <div className="h-[24vh] w-[24vh] rounded-2xl bg-gradient-to-br from-emerald-400 to-cyan-400 grid place-items-center"><div className="font-display text-[2vw] font-black text-[#0a0a14]">SCAN</div></div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>23 / 84</span></div>
    </div>
  );
}
