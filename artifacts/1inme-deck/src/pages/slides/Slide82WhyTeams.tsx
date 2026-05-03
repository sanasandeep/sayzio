export default function Slide82WhyTeams() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.2),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Why teams choose 1INME</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">Three reasons we keep hearing.</h2>

        <div className="mt-[5vh] grid grid-cols-3 gap-[2vw]">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[2vw] flex flex-col">
            <div className="font-display text-[1.1vw] uppercase tracking-[0.3em] text-violet-300">01</div>
            <div className="mt-[1vh] font-display text-[2.2vw] font-bold tracking-tight">One bill, less sprawl</div>
            <p className="mt-[1.5vh] text-[1.2vw] text-slate-300 leading-snug">Replaces the link tool, the QR tool, the form tool, the password tool, the booking tool, and at least one AI subscription.</p>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[2vw] flex flex-col">
            <div className="font-display text-[1.1vw] uppercase tracking-[0.3em] text-fuchsia-300">02</div>
            <div className="mt-[1vh] font-display text-[2.2vw] font-bold tracking-tight">Identity-aware AI</div>
            <p className="mt-[1.5vh] text-[1.2vw] text-slate-300 leading-snug">Every Mind, Coach, and workflow runs against your real data &mdash; not a generic chatbot pretending to know you.</p>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[2vw] flex flex-col">
            <div className="font-display text-[1.1vw] uppercase tracking-[0.3em] text-cyan-300">03</div>
            <div className="mt-[1vh] font-display text-[2.2vw] font-bold tracking-tight">Built to be exited</div>
            <p className="mt-[1.5vh] text-[1.2vw] text-slate-300 leading-snug">Open exports, custom domains, and standard formats. If you ever leave, you take everything with you.</p>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>82 / 84</span></div>
    </div>
  );
}
