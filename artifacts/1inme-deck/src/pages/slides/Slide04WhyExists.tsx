export default function Slide04WhyExists() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_right,rgba(124,58,237,0.2),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Why we exist</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[14vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-7 flex flex-col justify-center">
          <h2 className="font-display text-[4.4vw] font-bold leading-[1.02] tracking-tight">People are scattered across thirty tools they didn&rsquo;t choose.</h2>
          <p className="mt-[3vh] text-[1.5vw] text-slate-300 max-w-[42vw] leading-snug">Linktree for links. Notion for notes. Calendly for meetings. Stripe for payments. ChatGPT for thinking. Each one owns a slice of you.</p>
          <p className="mt-[2vh] text-[1.5vw] text-fuchsia-200 font-semibold max-w-[40vw] leading-snug">1INME brings the slice back to the whole.</p>
        </div>

        <div className="col-span-5 flex flex-col justify-center gap-[1.4vh]">
          <div className="flex items-center gap-[1.2vw]"><div className="h-[3vw] w-[3vw] rounded-xl border border-violet-400/40 bg-violet-500/10 grid place-items-center font-display text-[1.4vw] text-violet-200 font-bold">1</div><div className="text-[1.3vw] text-slate-200">A single identity, owned by you</div></div>
          <div className="flex items-center gap-[1.2vw]"><div className="h-[3vw] w-[3vw] rounded-xl border border-violet-400/40 bg-violet-500/10 grid place-items-center font-display text-[1.4vw] text-violet-200 font-bold">2</div><div className="text-[1.3vw] text-slate-200">A single workspace, not thirty tabs</div></div>
          <div className="flex items-center gap-[1.2vw]"><div className="h-[3vw] w-[3vw] rounded-xl border border-violet-400/40 bg-violet-500/10 grid place-items-center font-display text-[1.4vw] text-violet-200 font-bold">3</div><div className="text-[1.3vw] text-slate-200">A single bill, not a SaaS spreadsheet</div></div>
          <div className="flex items-center gap-[1.2vw]"><div className="h-[3vw] w-[3vw] rounded-xl border border-violet-400/40 bg-violet-500/10 grid place-items-center font-display text-[1.4vw] text-violet-200 font-bold">4</div><div className="text-[1.3vw] text-slate-200">AI that knows your context, not a stranger&rsquo;s</div></div>
        </div>
      </div>

      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>04 / 84</span></div>
    </div>
  );
}
