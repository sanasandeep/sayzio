const base = import.meta.env.BASE_URL;

export default function Slide16BiolinksHome() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <img src={`${base}hero-biolink.png`} crossOrigin="anonymous" alt="" className="absolute inset-0 w-full h-full object-cover opacity-35" />
      <div className="absolute inset-0 bg-[linear-gradient(110deg,rgba(10,10,20,0.96)_0%,rgba(10,10,20,0.75)_50%,rgba(10,10,20,0.4)_100%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-300">Biolinks · Home</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[14vh] pb-[8vh] flex flex-col">
        <span className="text-[1vw] uppercase tracking-[0.3em] text-fuchsia-300">Your home on the internet</span>
        <h2 className="mt-[1.5vh] font-display text-[4.6vw] font-bold leading-[1.02] tracking-tight max-w-[60vw]">Not a link page. A landing page that performs.</h2>
        <p className="mt-[2.5vh] text-[1.5vw] text-slate-200 max-w-[50vw] leading-snug">Hosted on edge infrastructure, indexable on Google, fully customisable down to the CSS &mdash; and routed through your own domain.</p>

        <div className="mt-[5vh] grid grid-cols-3 gap-[1.5vw] max-w-[65vw]">
          <div className="rounded-xl border border-white/15 bg-white/[0.06] p-[1.5vw]"><div className="font-display text-[1.5vw] font-semibold">Your handle, your URL</div><div className="mt-[0.6vh] text-[1.05vw] text-slate-300">1inme.com/you, or your own domain.</div></div>
          <div className="rounded-xl border border-white/15 bg-white/[0.06] p-[1.5vw]"><div className="font-display text-[1.5vw] font-semibold">100/100 Lighthouse</div><div className="mt-[0.6vh] text-[1.05vw] text-slate-300">Static, edge-cached, image-optimised.</div></div>
          <div className="rounded-xl border border-white/15 bg-white/[0.06] p-[1.5vw]"><div className="font-display text-[1.5vw] font-semibold">Owned forever</div><div className="mt-[0.6vh] text-[1.05vw] text-slate-300">Export, migrate, redirect &mdash; on your terms.</div></div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-400"><span>1inme.com</span><span>16 / 84</span></div>
    </div>
  );
}
