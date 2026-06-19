const base = import.meta.env.BASE_URL;

export default function Slide164Investormilestones() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Roadmap</span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.04] tracking-tight">Milestones.</h2>
        <p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[65vw]">What we&rsquo;ll prove with this round.</p>
        <div className="mt-[4vh] grid grid-cols-4 gap-[1.5vw] flex-1">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="text-[0.95vw] uppercase tracking-[0.3em] text-fuchsia-200">Quarter 1</div>
            <div className="mt-[0.5vh] font-display text-[1.8vw] font-semibold">Pricing + PLG funnel</div>
            <ul className="mt-[2vh] space-y-[0.8vh] text-[1.05vw] text-slate-300 leading-snug"><li>&middot; New pricing live</li><li>&middot; Self-serve to Pro upgrade</li><li>&middot; Activation up 25%</li></ul>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="text-[0.95vw] uppercase tracking-[0.3em] text-fuchsia-200">Quarter 2</div>
            <div className="mt-[0.5vh] font-display text-[1.8vw] font-semibold">AI + integrations</div>
            <ul className="mt-[2vh] space-y-[0.8vh] text-[1.05vw] text-slate-300 leading-snug"><li>&middot; Companions GA</li><li>&middot; 10 native integrations</li><li>&middot; AskCoach 2.0</li></ul>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="text-[0.95vw] uppercase tracking-[0.3em] text-fuchsia-200">Quarter 3</div>
            <div className="mt-[0.5vh] font-display text-[1.8vw] font-semibold">Workspaces + white label</div>
            <ul className="mt-[2vh] space-y-[0.8vh] text-[1.05vw] text-slate-300 leading-snug"><li>&middot; Agency tier</li><li>&middot; SSO + SCIM</li><li>&middot; First 50 white-label deals</li></ul>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="text-[0.95vw] uppercase tracking-[0.3em] text-fuchsia-200">Quarter 4</div>
            <div className="mt-[0.5vh] font-display text-[1.8vw] font-semibold">Mobile + enterprise</div>
            <ul className="mt-[2vh] space-y-[0.8vh] text-[1.05vw] text-slate-300 leading-snug"><li>&middot; Mobile parity</li><li>&middot; SOC 2 Type II</li><li>&middot; First enterprise wins</li></ul>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>165 / 189</span></div>
    </div>
  );
}
