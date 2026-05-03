export default function Slide43Forms() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(124,58,237,0.2),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Forms</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-6 flex flex-col justify-center">
          <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight">Forms that route, score, and reply.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[36vw] leading-snug">Capture leads, applications, intake, RSVPs &mdash; with conditional logic, file uploads, and AI scoring out of the box.</p>
          <div className="mt-[3vh] flex flex-col gap-[1vh] text-[1.2vw]">
            <div className="flex items-center gap-[0.8vw]"><span className="text-violet-300">&rarr;</span><span>Drag-to-build, no JavaScript</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-violet-300">&rarr;</span><span>Embed anywhere &mdash; biolink, site, email</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-violet-300">&rarr;</span><span>Native captcha &amp; spam filtering</span></div>
          </div>
        </div>
        <div className="col-span-6 flex items-center">
          <div className="w-full rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw] flex flex-col gap-[1.2vh]">
            <div className="font-display text-[1.5vw] font-semibold">Workshop application</div>
            <div className="text-[1vw] text-slate-400">Closes in 4 days &middot; 38 responses</div>
            <div className="mt-[1vh] flex flex-col gap-[1vh]">
              <div><div className="text-[1vw] text-slate-300">Full name</div><div className="mt-[0.5vh] h-[3.4vh] rounded-md bg-white/[0.04] border border-white/10" /></div>
              <div><div className="text-[1vw] text-slate-300">What are you working on?</div><div className="mt-[0.5vh] h-[7vh] rounded-md bg-white/[0.04] border border-white/10" /></div>
              <div><div className="text-[1vw] text-slate-300">Upload sample work</div><div className="mt-[0.5vh] h-[3.4vh] rounded-md bg-white/[0.04] border border-dashed border-white/15 flex items-center justify-center text-[1vw] text-slate-400">Drop files here</div></div>
            </div>
            <div className="mt-[0.8vh] inline-block px-[1vw] py-[0.4vh] rounded-md bg-violet-500/15 border border-violet-400/30 text-[0.95vw] text-violet-200 self-start">AI scoring on submit</div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>43 / 84</span></div>
    </div>
  );
}
