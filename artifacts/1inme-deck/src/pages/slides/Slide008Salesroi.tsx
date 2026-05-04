const base = import.meta.env.BASE_URL;

export default function Slide008Salesroi() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400"></span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.6vw] font-bold leading-[1.04] tracking-tight max-w-[65vw]">ROI of consolidating onto 1INME.</h2>
        
        <div className="mt-[5vh] grid grid-cols-4 gap-[1.5vw]">
          <div className="rounded-xl border border-white/10 bg-white/[0.03] p-[1.6vw]"><div className="font-display text-[2.8vw] font-bold text-violet-300">−$1,400</div><div className="mt-[0.5vh] text-[1.05vw] text-slate-300">annual stack savings (avg)</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.03] p-[1.6vw]"><div className="font-display text-[2.8vw] font-bold text-violet-300">+6h</div><div className="mt-[0.5vh] text-[1.05vw] text-slate-300">weekly time saved</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.03] p-[1.6vw]"><div className="font-display text-[2.8vw] font-bold text-violet-300">+24%</div><div className="mt-[0.5vh] text-[1.05vw] text-slate-300">lead-to-close conversion</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.03] p-[1.6vw]"><div className="font-display text-[2.8vw] font-bold text-violet-300">1 invoice</div><div className="mt-[0.5vh] text-[1.05vw] text-slate-300">across every workspace</div></div>
        </div>
        <p className="mt-[4vh] text-[1vw] text-slate-500 max-w-[60vw]">Aggregate self-reported numbers from early-access customers, 2025 cohort.</p>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>8 / 188</span></div>
    </div>
  );
}
