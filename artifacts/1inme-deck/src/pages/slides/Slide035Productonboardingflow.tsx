const base = import.meta.env.BASE_URL;

export default function Slide035Productonboardingflow() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400"></span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.04] tracking-tight max-w-[65vw]">The onboarding flow.</h2>
        <p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[65vw]">Time-to-value measured in hours, not weeks.</p>
        <div className="mt-[4vh] flex-1 ">
          <ul className="space-y-[1.6vh]">            <li className="flex gap-[1vw]"><span className="font-display text-[1.4vw] text-fuchsia-300 leading-none">&rarr;</span><div><div className="font-display text-[1.4vw] font-semibold">Detect</div><div className="mt-[0.4vh] text-[1.05vw] text-slate-300 leading-snug">We scan your existing handles and brand kit.</div></div></li>
            <li className="flex gap-[1vw]"><span className="font-display text-[1.4vw] text-fuchsia-300 leading-none">&rarr;</span><div><div className="font-display text-[1.4vw] font-semibold">Import</div><div className="mt-[0.4vh] text-[1.05vw] text-slate-300 leading-snug">Linktree, Bitly, HubSpot, CSV — pick what you have.</div></div></li>
            <li className="flex gap-[1vw]"><span className="font-display text-[1.4vw] text-fuchsia-300 leading-none">&rarr;</span><div><div className="font-display text-[1.4vw] font-semibold">Wire</div><div className="mt-[0.4vh] text-[1.05vw] text-slate-300 leading-snug">Pixels, integrations, and CRM in one click each.</div></div></li>
            <li className="flex gap-[1vw]"><span className="font-display text-[1.4vw] text-fuchsia-300 leading-none">&rarr;</span><div><div className="font-display text-[1.4vw] font-semibold">Launch</div><div className="mt-[0.4vh] text-[1.05vw] text-slate-300 leading-snug">First campaign live before the kickoff call ends.</div></div></li></ul>
          
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>36 / 189</span></div>
    </div>
  );
}
