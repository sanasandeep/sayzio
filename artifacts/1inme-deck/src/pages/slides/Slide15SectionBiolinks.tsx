export default function Slide15SectionBiolinks() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#14091f] text-slate-50 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_25%_30%,rgba(124,58,237,0.45),transparent_50%),radial-gradient(circle_at_80%_75%,rgba(236,72,153,0.35),transparent_55%)]" />
      <div className="absolute inset-0 bg-[linear-gradient(180deg,transparent,rgba(0,0,0,0.45))]" />

      <div className="relative h-full w-full px-[8vw] py-[7vh] flex flex-col justify-center">
        <span className="font-display text-[1.2vw] uppercase tracking-[0.5em] text-fuchsia-200">Section 03</span>
        <h2 className="mt-[2vh] font-display text-[8vw] font-bold leading-[0.92] tracking-tight">Biolinks.</h2>
        <p className="mt-[3vh] text-[1.7vw] text-slate-200 max-w-[55vw] leading-snug">A profile page that finally feels like you &mdash; built for conversion, owned forever, faster than every alternative.</p>
      </div>

      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-300"><span>1inme.com</span><span>15 / 84</span></div>
    </div>
  );
}
