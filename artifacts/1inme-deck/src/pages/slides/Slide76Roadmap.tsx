const base = import.meta.env.BASE_URL;

export default function Slide76Roadmap() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.2),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">What ships next</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">The next four quarters.</h2>

        <div className="mt-[5vh] grid grid-cols-4 gap-[1.5vw]">
          <div className="rounded-2xl border border-emerald-400/30 bg-emerald-500/10 p-[1.8vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-emerald-200">Q2 &middot; Shipping</div><div className="mt-[1vh] flex flex-col gap-[0.8vh] text-[1.1vw] text-slate-200"><div>Card &amp; brochure scanner</div><div>Voice Assistant 2.0</div><div>Custom Mind tools</div><div>NFC card v2</div></div></div>
          <div className="rounded-2xl border border-violet-400/30 bg-violet-500/10 p-[1.8vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-violet-200">Q3 &middot; Building</div><div className="mt-[1vh] flex flex-col gap-[0.8vh] text-[1.1vw] text-slate-200"><div>Workflow marketplace</div><div>Invoicing &amp; quotes</div><div>Multi-language Coach</div><div>SCIM directory sync</div></div></div>
          <div className="rounded-2xl border border-fuchsia-400/30 bg-fuchsia-500/10 p-[1.8vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-fuchsia-200">Q4 &middot; Planning</div><div className="mt-[1vh] flex flex-col gap-[0.8vh] text-[1.1vw] text-slate-200"><div>Native desktop app</div><div>1INME Pages (long-form)</div><div>On-device AI</div><div>Public app marketplace</div></div></div>
          <div className="rounded-2xl border border-cyan-400/30 bg-cyan-500/10 p-[1.8vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-cyan-200">Q1 &middot; Considering</div><div className="mt-[1vh] flex flex-col gap-[0.8vh] text-[1.1vw] text-slate-200"><div>Community marketplace</div><div>Wearables companion</div><div>Localised payouts</div><div>Audit-only role</div></div></div>
        </div>

        <p className="mt-[4vh] text-[1.1vw] text-slate-400">Roadmap voting is open at 1inme.com/roadmap. Anything you upvote, we read.</p>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>76 / 84</span></div>
    </div>
  );
}
