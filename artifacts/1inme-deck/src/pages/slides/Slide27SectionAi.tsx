const base = import.meta.env.BASE_URL;

export default function Slide27SectionAi() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#14091f] text-slate-50 font-body">
      <img src={`${base}hero-ai.png`} crossOrigin="anonymous" alt="" className="absolute inset-0 w-full h-full object-cover opacity-40" />
      <div className="absolute inset-0 bg-[linear-gradient(120deg,rgba(20,9,31,0.88),rgba(20,9,31,0.55)_60%,rgba(20,9,31,0.3))]" />

      <div className="relative h-full w-full px-[8vw] py-[7vh] flex flex-col justify-center">
        <span className="font-display text-[1.2vw] uppercase tracking-[0.5em] text-fuchsia-200">Section 05</span>
        <h2 className="mt-[2vh] font-display text-[8vw] font-bold leading-[0.92] tracking-tight">AI Ecosystem.</h2>
        <p className="mt-[3vh] text-[1.7vw] text-slate-200 max-w-[55vw] leading-snug">Five products, one credit system. Companions for play, Minds for work, a Coach for growth, a Voice for hands-free, and a Scanner for the field.</p>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-300"><span>1inme.com</span><span>27 / 84</span></div>
    </div>
  );
}
