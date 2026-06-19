const base = import.meta.env.BASE_URL;

export default function Slide128Coachesexpertsfitnesstrainer() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400"></span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.04] tracking-tight max-w-[65vw]">Meet the fitness trainers.</h2>
        <p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[65vw]">Coaches &amp; Experts. Here are the top pains we hear over and over.</p>
        <div className="mt-[4vh] flex-1 grid grid-cols-12 gap-[2vw]">
          <ul className="col-span-7 space-y-[1.6vh]">            <li className="flex gap-[1vw]"><span className="font-display text-[1.4vw] text-fuchsia-300 leading-none">&rarr;</span><div><div className="font-display text-[1.4vw] font-semibold">Plans, payments, and progress in different apps</div></div></li>
            <li className="flex gap-[1vw]"><span className="font-display text-[1.4vw] text-fuchsia-300 leading-none">&rarr;</span><div><div className="font-display text-[1.4vw] font-semibold">Client retention drops after sign-up</div></div></li>
            <li className="flex gap-[1vw]"><span className="font-display text-[1.4vw] text-fuchsia-300 leading-none">&rarr;</span><div><div className="font-display text-[1.4vw] font-semibold">No mobile-first capture</div></div></li></ul>
          <div className="col-span-5 rounded-2xl border border-white/10 bg-white/[0.04] p-[2vw] flex flex-col justify-center">
            <div className="text-[1vw] uppercase tracking-[0.3em] text-fuchsia-200">Why now</div>
            <div className="mt-[1vh] font-display text-[2vw] font-semibold leading-snug">Their stack hit a wall.</div>
            <div className="mt-[1.5vh] text-[1.1vw] text-slate-300 leading-snug">More tools is no longer the answer. They want one home that respects their craft.</div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>129 / 189</span></div>
    </div>
  );
}
