const base = import.meta.env.BASE_URL;

export default function SubdeckCover() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <img src={`${base}hero-cover.png`} crossOrigin="anonymous" alt="" className="absolute inset-0 w-full h-full object-cover opacity-60" />
      <div className="absolute inset-0 bg-[linear-gradient(120deg,rgba(10,10,20,0.95)_0%,rgba(20,9,31,0.78)_45%,rgba(10,10,20,0.55)_100%)]" />
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_right,rgba(236,72,153,0.25),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="Sayzio" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Sayzio</span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <div className="flex-1 flex flex-col justify-center max-w-[80vw]">
          <span className="inline-block self-start px-[1.2vw] py-[0.6vh] rounded-full border border-fuchsia-400/40 bg-fuchsia-500/10 text-[1vw] tracking-[0.25em] uppercase text-fuchsia-200">Sub-deck · Investor Pitch</span>
          <h1 className="mt-[3vh] font-display text-[7.5vw] font-bold tracking-tight leading-[0.92]">Sayzio.<span className="block bg-gradient-to-r from-violet-300 via-fuchsia-300 to-pink-200 bg-clip-text text-transparent">For investor.</span><span className="block text-slate-200">One platform.</span></h1>
          <p className="mt-[3vh] text-[1.6vw] text-slate-300 max-w-[60vw] leading-snug">A trimmed deck containing only: Investor Pitch.</p>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>1 / 23</span></div>
    </div>
  );
}
