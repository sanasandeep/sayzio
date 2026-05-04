const base = import.meta.env.BASE_URL;

export default function Slide014Salesobjections() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400"></span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.04] tracking-tight max-w-[65vw]">What buyers ask, and how we answer.</h2>
        <p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[65vw]">The four objections we hear most often — and the proof points to handle them.</p>
        <div className="mt-[4vh] grid grid-cols-3 gap-[1.4vw] flex-1 content-start">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="text-[0.85vw] uppercase tracking-[0.25em] text-fuchsia-200">&ldquo;We already use X&ldquo;</div>
            <div className="font-display text-[1.5vw] font-semibold mt-[0.5vh]">Migration in days</div>
            <div className="mt-[1vh] text-[1.05vw] text-slate-300 leading-snug">Importers for Linktree, Bitly, HubSpot, Calendly, and CSV.</div>
            
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="text-[0.85vw] uppercase tracking-[0.25em] text-fuchsia-200">&ldquo;Too many features&ldquo;</div>
            <div className="font-display text-[1.5vw] font-semibold mt-[0.5vh]">Modular by design</div>
            <div className="mt-[1vh] text-[1.05vw] text-slate-300 leading-snug">Turn modules off per workspace. Pay only for what you use.</div>
            
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="text-[0.85vw] uppercase tracking-[0.25em] text-fuchsia-200">&ldquo;AI is expensive&ldquo;</div>
            <div className="font-display text-[1.5vw] font-semibold mt-[0.5vh]">Pooled credits</div>
            <div className="mt-[1vh] text-[1.05vw] text-slate-300 leading-snug">Credits shared across the workspace, top-up on demand.</div>
            
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="text-[0.85vw] uppercase tracking-[0.25em] text-fuchsia-200">&ldquo;Security?&ldquo;</div>
            <div className="font-display text-[1.5vw] font-semibold mt-[0.5vh]">Built for trust</div>
            <div className="mt-[1vh] text-[1.05vw] text-slate-300 leading-snug">MFA, passkeys, audit log, SOC 2 trajectory, SSO + SCIM.</div>
            
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>14 / 188</span></div>
    </div>
  );
}
