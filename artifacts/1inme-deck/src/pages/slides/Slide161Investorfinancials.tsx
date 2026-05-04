const base = import.meta.env.BASE_URL;

export default function Slide161Investorfinancials() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Quarterly view</span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.2vw] font-bold leading-[1.04] tracking-tight">Financials &amp; projections (placeholders).</h2>
        <p className="mt-[2vh] text-[1.2vw] text-slate-300 max-w-[65vw]">Replace with the live model before sending.</p>
        <div className="mt-[4vh] flex-1 flex flex-col">
          <div className="grid grid-cols-5 gap-[1vw] pb-[1vh]"><div className="text-[0.9vw] uppercase tracking-[0.25em] text-slate-400 ">Metric</div><div className="text-[0.9vw] uppercase tracking-[0.25em] text-slate-400 ">Y1</div><div className="text-[0.9vw] uppercase tracking-[0.25em] text-slate-400 ">Y2</div><div className="text-[0.9vw] uppercase tracking-[0.25em] text-slate-400 ">Y3</div><div className="text-[0.9vw] uppercase tracking-[0.25em] text-slate-400 ">Y4</div></div>
            <div className="grid grid-cols-5 gap-[1vw] py-[1vh] border-t border-white/10">
              <div className="font-display text-[1.1vw] font-semibold text-violet-200">ARR</div>
              <div className="text-[0.95vw] text-slate-300">$1.2M</div><div className="text-[0.95vw] text-slate-300">$5.4M</div><div className="text-[0.95vw] text-slate-300">$14M</div><div className="text-[0.95vw] text-slate-300">$32M</div>
            </div>
            <div className="grid grid-cols-5 gap-[1vw] py-[1vh] border-t border-white/10">
              <div className="font-display text-[1.1vw] font-semibold text-violet-200">Gross margin</div>
              <div className="text-[0.95vw] text-slate-300">72%</div><div className="text-[0.95vw] text-slate-300">78%</div><div className="text-[0.95vw] text-slate-300">81%</div><div className="text-[0.95vw] text-slate-300">82%</div>
            </div>
            <div className="grid grid-cols-5 gap-[1vw] py-[1vh] border-t border-white/10">
              <div className="font-display text-[1.1vw] font-semibold text-violet-200">Net revenue retention</div>
              <div className="text-[0.95vw] text-slate-300">108%</div><div className="text-[0.95vw] text-slate-300">118%</div><div className="text-[0.95vw] text-slate-300">125%</div><div className="text-[0.95vw] text-slate-300">128%</div>
            </div>
            <div className="grid grid-cols-5 gap-[1vw] py-[1vh] border-t border-white/10">
              <div className="font-display text-[1.1vw] font-semibold text-violet-200">Burn multiple</div>
              <div className="text-[0.95vw] text-slate-300">1.8</div><div className="text-[0.95vw] text-slate-300">1.2</div><div className="text-[0.95vw] text-slate-300">0.8</div><div className="text-[0.95vw] text-slate-300">0.6</div>
            </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>161 / 188</span></div>
    </div>
  );
}
