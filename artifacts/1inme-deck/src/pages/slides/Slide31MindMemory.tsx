const base = import.meta.env.BASE_URL;

export default function Slide31MindMemory() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.16),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">AI · Memory &amp; training</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight max-w-[60vw]">Train it on what only you know.</h2>
        <p className="mt-[2vh] text-[1.4vw] text-slate-300 max-w-[55vw]">Drop in PDFs, web pages, transcripts, files &mdash; or stream from your CRM, drive, and notes. The Mind learns. You stay in control.</p>

        <div className="mt-[5vh] grid grid-cols-4 gap-[1.5vw]">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="font-display text-[1.4vw] font-semibold">Documents</div><div className="text-[1.05vw] text-slate-400 mt-[0.5vh]">PDF, DOCX, MD, TXT</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="font-display text-[1.4vw] font-semibold">Web sources</div><div className="text-[1.05vw] text-slate-400 mt-[0.5vh]">URLs &amp; sitemaps</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="font-display text-[1.4vw] font-semibold">Live data</div><div className="text-[1.05vw] text-slate-400 mt-[0.5vh]">CRM, drive, calendar</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="font-display text-[1.4vw] font-semibold">Voice notes</div><div className="text-[1.05vw] text-slate-400 mt-[0.5vh]">Transcribed &amp; indexed</div></div>
        </div>

        <p className="mt-[5vh] text-[1.2vw] text-slate-400 max-w-[55vw]">Memory is scoped per Mind. Forget anything with one click. Audit logs included.</p>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>31 / 84</span></div>
    </div>
  );
}
