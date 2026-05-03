export default function Slide45CloudFiles() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(34,211,238,0.14),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Cloud Files</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">Files at the speed of a link.</h2>
        <p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[55vw]">Upload once, share everywhere &mdash; with edge caching, expirable links, and built-in viewer.</p>

        <div className="mt-[5vh] grid grid-cols-4 gap-[1.5vw]">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-cyan-300">Upload</div><div className="mt-[1vh] font-display text-[1.5vw] font-semibold">Drag &amp; drop</div><div className="text-[1.05vw] text-slate-300 mt-[0.5vh]">Resumable, multi-part for large files.</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-violet-300">Share</div><div className="mt-[1vh] font-display text-[1.5vw] font-semibold">Smart links</div><div className="text-[1.05vw] text-slate-300 mt-[0.5vh]">Expiry, password, request approval.</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-fuchsia-300">Preview</div><div className="mt-[1vh] font-display text-[1.5vw] font-semibold">In-browser viewer</div><div className="text-[1.05vw] text-slate-300 mt-[0.5vh]">PDF, video, image, doc, audio.</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-emerald-300">Track</div><div className="mt-[1vh] font-display text-[1.5vw] font-semibold">View &amp; download stats</div><div className="text-[1.05vw] text-slate-300 mt-[0.5vh]">See who opened it and when.</div></div>
        </div>

        <p className="mt-[4vh] text-[1.2vw] text-slate-400 max-w-[60vw]">Everything is encrypted at rest and served from the closest edge to the recipient.</p>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>45 / 84</span></div>
    </div>
  );
}
