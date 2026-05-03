export default function Slide02Agenda() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(124,58,237,0.22),transparent_55%)]" />

      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]">
        <div className="flex items-center gap-[0.7vw]">
          <div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" />
          <span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span>
        </div>
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Agenda</span>
      </div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-5 flex flex-col justify-center">
          <span className="text-[1vw] uppercase tracking-[0.3em] text-fuchsia-300">What we&rsquo;ll cover</span>
          <h2 className="mt-[1.5vh] font-display text-[4.4vw] font-bold leading-[1.02] tracking-tight">A guided tour of the 1INME stack.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[28vw] leading-snug">Eleven modules, one identity. We&rsquo;ll cover the platform from biolink to billing.</p>
        </div>

        <div className="col-span-7 grid grid-cols-2 gap-[1.5vw] content-center">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[2vw]"><div className="text-[1vw] text-violet-300 font-semibold">01</div><div className="mt-[0.5vh] font-display text-[1.6vw] font-semibold">Vision &amp; Audience</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[2vw]"><div className="text-[1vw] text-violet-300 font-semibold">02</div><div className="mt-[0.5vh] font-display text-[1.6vw] font-semibold">Web · Mobile · API</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[2vw]"><div className="text-[1vw] text-violet-300 font-semibold">03</div><div className="mt-[0.5vh] font-display text-[1.6vw] font-semibold">Biolinks · Links · QR</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[2vw]"><div className="text-[1vw] text-violet-300 font-semibold">04</div><div className="mt-[0.5vh] font-display text-[1.6vw] font-semibold">AI Ecosystem</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[2vw]"><div className="text-[1vw] text-violet-300 font-semibold">05</div><div className="mt-[0.5vh] font-display text-[1.6vw] font-semibold">Vault &amp; Productivity</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[2vw]"><div className="text-[1vw] text-violet-300 font-semibold">06</div><div className="mt-[0.5vh] font-display text-[1.6vw] font-semibold">Social &amp; Mobile-only</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[2vw]"><div className="text-[1vw] text-violet-300 font-semibold">07</div><div className="mt-[0.5vh] font-display text-[1.6vw] font-semibold">Admin · Billing · Plans</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[2vw]"><div className="text-[1vw] text-violet-300 font-semibold">08</div><div className="mt-[0.5vh] font-display text-[1.6vw] font-semibold">Analytics · Integrations · Security</div></div>
        </div>
      </div>

      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500">
        <span>1inme.com</span>
        <span>02 / 84</span>
      </div>
    </div>
  );
}
