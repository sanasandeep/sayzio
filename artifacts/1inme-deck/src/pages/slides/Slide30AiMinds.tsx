const base = import.meta.env.BASE_URL;

export default function Slide30AiMinds() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.22),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">AI · Minds</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-6 flex flex-col justify-center">
          <span className="text-[1vw] uppercase tracking-[0.3em] text-violet-300">AI Minds</span>
          <h2 className="mt-[1.5vh] font-display text-[3.8vw] font-bold leading-[1.02] tracking-tight">Dedicated workspaces for serious work.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[36vw] leading-snug">A Mind is an agent with a purpose, a memory, and a toolset &mdash; one for sales, one for content, one for operations.</p>
          <div className="mt-[3vh] flex flex-col gap-[1vh] text-[1.2vw]">
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Persistent context across sessions</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Read &amp; write to your Vault, files, CRM</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Scoped permissions per Mind</span></div>
          </div>
        </div>
        <div className="col-span-6 flex flex-col gap-[1.2vw] justify-center">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex items-center gap-[1.5vw]"><div className="h-[3vw] w-[3vw] rounded-xl bg-gradient-to-br from-violet-400 to-fuchsia-400 grid place-items-center font-display text-[1.4vw] font-bold">S</div><div><div className="font-display text-[1.5vw] font-semibold">Sales Mind</div><div className="text-[1.05vw] text-slate-300">Lead enrichment &middot; outreach drafts &middot; CRM updates</div></div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex items-center gap-[1.5vw]"><div className="h-[3vw] w-[3vw] rounded-xl bg-gradient-to-br from-fuchsia-400 to-rose-400 grid place-items-center font-display text-[1.4vw] font-bold">C</div><div><div className="font-display text-[1.5vw] font-semibold">Content Mind</div><div className="text-[1.05vw] text-slate-300">Brief &middot; outline &middot; draft &middot; cross-post</div></div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex items-center gap-[1.5vw]"><div className="h-[3vw] w-[3vw] rounded-xl bg-gradient-to-br from-cyan-400 to-emerald-400 grid place-items-center font-display text-[1.4vw] font-bold">O</div><div><div className="font-display text-[1.5vw] font-semibold">Ops Mind</div><div className="text-[1.05vw] text-slate-300">SOPs &middot; standups &middot; weekly reports</div></div></div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>30 / 84</span></div>
    </div>
  );
}
