const base = import.meta.env.BASE_URL;

export default function Slide20SectionLinks() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#14091f] text-slate-50 font-body">
      <img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="absolute top-[5vh] left-[5vw] h-[2.4vw] w-auto z-10" />
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_30%,rgba(236,72,153,0.4),transparent_50%),radial-gradient(circle_at_75%_75%,rgba(124,58,237,0.4),transparent_55%)]" />
      <div className="absolute inset-0 bg-[linear-gradient(180deg,transparent,rgba(0,0,0,0.45))]" />

      <div className="relative h-full w-full px-[8vw] py-[7vh] flex flex-col justify-center">
        <span className="font-display text-[1.2vw] uppercase tracking-[0.5em] text-fuchsia-200">Section 04</span>
        <h2 className="mt-[2vh] font-display text-[8vw] font-bold leading-[0.92] tracking-tight">Dynamic Links<span className="block text-fuchsia-200">&amp; QR.</span></h2>
        <p className="mt-[3vh] text-[1.7vw] text-slate-200 max-w-[55vw] leading-snug">Links that adapt by country, device, language, and time &mdash; with QR codes that look like part of your brand.</p>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-300"><span>1inme.com</span><span>20 / 84</span></div>
    </div>
  );
}
