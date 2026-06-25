const base = import.meta.env.BASE_URL;

export default function Slide097Creatorsmusiciansdivider() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#14091f] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_30%,rgba(124,58,237,0.45),transparent_50%),radial-gradient(circle_at_75%_75%,rgba(236,72,153,0.35),transparent_55%)]" />
      <div className="absolute inset-0 bg-[linear-gradient(180deg,transparent,rgba(0,0,0,0.45))]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="Sayzio" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Appendix</span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <div className="flex-1 flex flex-col justify-center">
          <span className="font-display text-[1.1vw] uppercase tracking-[0.5em] text-fuchsia-200">Persona appendix</span>
          <h2 className="mt-[2vh] font-display text-[7vw] font-bold leading-[0.94] tracking-tight max-w-[80vw]">How Sayzio helps a musicians.</h2>
          <p className="mt-[3vh] text-[1.7vw] text-slate-200 max-w-[60vw] leading-snug">Creators · 4 slides + this divider.</p>
          <div className="mt-[5vh] inline-flex items-center gap-[1.5vw]">
            <div className="h-[0.4vh] w-[6vw] bg-gradient-to-r from-violet-400 to-fuchsia-400 rounded-full" />
            <span className="text-[1vw] uppercase tracking-[0.3em] text-slate-300">4 slides</span>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>98 / 189</span></div>
    </div>
  );
}
