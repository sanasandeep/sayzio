const base = import.meta.env.BASE_URL;

export default function Slide010Salesproofquote() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="Sayzio" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Voice of the user</span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <div className="flex-1 flex flex-col justify-center max-w-[80vw]">
          <span className="text-[1vw] uppercase tracking-[0.3em] text-fuchsia-200">Persona quote</span>
          <blockquote className="mt-[3vh] font-display text-[4vw] font-semibold leading-[1.1] tracking-tight">&ldquo;We replaced six tools and shaved a workday off every week. The team finally has one place to live.&rdquo;</blockquote>
          <div className="mt-[4vh] flex items-center gap-[1.5vw]">
            <div className="h-[5vw] w-[5vw] rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 grid place-items-center font-display text-[2vw] font-bold">A</div>
            <div><div className="font-display text-[1.4vw] font-semibold">Avery K.</div><div className="text-[1vw] text-slate-400">Founder, indie record label · early-access customer</div></div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>10 / 189</span></div>
    </div>
  );
}
