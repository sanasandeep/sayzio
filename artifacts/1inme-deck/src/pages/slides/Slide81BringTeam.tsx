const base = import.meta.env.BASE_URL;

export default function Slide81BringTeam() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Bring your team</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-6 flex flex-col justify-center">
          <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight">A workspace ready for the second seat.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[36vw] leading-snug">Invite by email or domain, pick a role, and they&rsquo;re inside &mdash; with the right access, no manual provisioning.</p>
          <div className="mt-[3vh] flex flex-col gap-[1vh] text-[1.2vw]">
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Bulk import via CSV or SCIM</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Per-team vaults, links, and CRM lists</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>One-click migrate from Linktree, Bitly, Beacons</span></div>
          </div>
        </div>
        <div className="col-span-6 flex items-center">
          <div className="w-full rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw] flex flex-col gap-[1.2vh]">
            <div className="flex items-center gap-[1vw]"><div className="h-[2.6vw] w-[2.6vw] rounded-full bg-gradient-to-br from-violet-400 to-fuchsia-400 grid place-items-center font-display text-[1vw] font-bold">M</div><div className="flex-1"><div className="font-display text-[1.3vw] font-semibold">Mira Okafor</div><div className="text-[1vw] text-slate-400">mira@aurora.co</div></div><div className="text-[0.95vw] px-[0.7vw] py-[0.2vh] rounded bg-violet-500/15 border border-violet-400/30 text-violet-200">Owner</div></div>
            <div className="flex items-center gap-[1vw]"><div className="h-[2.6vw] w-[2.6vw] rounded-full bg-gradient-to-br from-cyan-400 to-emerald-300 grid place-items-center font-display text-[1vw] font-bold text-[#0a0a14]">J</div><div className="flex-1"><div className="font-display text-[1.3vw] font-semibold">Jonas Weil</div><div className="text-[1vw] text-slate-400">jonas@aurora.co</div></div><div className="text-[0.95vw] px-[0.7vw] py-[0.2vh] rounded bg-fuchsia-500/15 border border-fuchsia-400/30 text-fuchsia-200">Editor</div></div>
            <div className="flex items-center gap-[1vw]"><div className="h-[2.6vw] w-[2.6vw] rounded-full bg-gradient-to-br from-amber-400 to-rose-400 grid place-items-center font-display text-[1vw] font-bold">A</div><div className="flex-1"><div className="font-display text-[1.3vw] font-semibold">Aiko Tanaka</div><div className="text-[1vw] text-slate-400">aiko@aurora.co</div></div><div className="text-[0.95vw] px-[0.7vw] py-[0.2vh] rounded bg-cyan-500/15 border border-cyan-400/30 text-cyan-200">Viewer</div></div>
            <div className="flex items-center gap-[1vw] opacity-70"><div className="h-[2.6vw] w-[2.6vw] rounded-full border border-dashed border-white/30 grid place-items-center text-[1.1vw] text-slate-400">+</div><div className="flex-1"><div className="font-display text-[1.3vw] font-semibold text-slate-300">Invite by email or domain</div><div className="text-[1vw] text-slate-500">@aurora.co auto-joins</div></div></div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>81 / 84</span></div>
    </div>
  );
}
