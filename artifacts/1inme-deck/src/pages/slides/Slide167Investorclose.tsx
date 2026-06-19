const base = import.meta.env.BASE_URL;

export default function Slide167Investorclose() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_30%,rgba(124,58,237,0.4),transparent_55%),radial-gradient(circle_at_75%_75%,rgba(236,72,153,0.35),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Get in touch</span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <div className="flex-1 flex flex-col justify-center">
          <span className="font-display text-[1.1vw] uppercase tracking-[0.5em] text-fuchsia-200">Thank you</span>
          <h1 className="mt-[2vh] font-display text-[8vw] font-bold tracking-tight leading-[0.92]">Let&rsquo;s build the everything platform.</h1>
          <p className="mt-[3vh] text-[1.6vw] text-slate-200 max-w-[60vw] leading-snug">Happy to share the live model, customer references, and a deeper product session.</p>
          <div className="mt-[6vh] grid grid-cols-3 gap-[2vw] max-w-[70vw]">
            <div><div className="text-[0.95vw] uppercase tracking-[0.3em] text-fuchsia-200">Founder</div><div className="mt-[0.5vh] font-display text-[1.5vw] font-semibold">[ name ]</div></div><div><div className="text-[0.95vw] uppercase tracking-[0.3em] text-fuchsia-200">Email</div><div className="mt-[0.5vh] font-display text-[1.5vw] font-semibold">investors@1inme.com</div></div><div><div className="text-[0.95vw] uppercase tracking-[0.3em] text-fuchsia-200">Data room</div><div className="mt-[0.5vh] font-display text-[1.5vw] font-semibold">1inme.com/dataroom</div></div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>168 / 189</span></div>
    </div>
  );
}
