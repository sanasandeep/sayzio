const base = import.meta.env.BASE_URL;

export default function Slide01Cover() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-50 font-body">
      <img src={`${base}hero-cover.png`} crossOrigin="anonymous" alt="" className="absolute inset-0 w-full h-full object-cover opacity-70" />
      <div className="absolute inset-0 bg-[linear-gradient(120deg,rgba(10,10,20,0.92)_0%,rgba(20,9,31,0.78)_45%,rgba(10,10,20,0.55)_100%)]" />
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_right,rgba(236,72,153,0.25),transparent_55%)]" />

      <div className="relative h-full w-full px-[7vw] py-[7vh] flex flex-col justify-between">
        <div className="flex items-center gap-[1vw]">
          <img src={`${base}logo-1inme.png`} crossOrigin="anonymous" alt="1INME" className="h-[3vw] w-auto" />
        </div>

        <div className="max-w-[70vw]">
          <span className="inline-block px-[1.2vw] py-[0.6vh] rounded-full border border-fuchsia-400/40 bg-fuchsia-500/10 text-[1vw] tracking-[0.25em] uppercase text-fuchsia-200">Product Deck · 2026</span>
          <h1 className="mt-[3vh] font-display text-[7.5vw] font-bold tracking-tight leading-[0.95]">One link.<span className="block bg-gradient-to-r from-violet-300 via-fuchsia-300 to-pink-200 bg-clip-text text-transparent">One identity.</span><span className="block text-slate-200">One platform.</span></h1>
          <p className="mt-[3vh] text-[1.7vw] text-slate-300 max-w-[55vw] leading-snug">The everything platform for creators, professionals, and teams &mdash; biolinks, dynamic links, AI agents, vault, productivity, and analytics in one place.</p>
        </div>

        <div className="flex items-center justify-between text-[1vw] text-slate-400">
          <span className="tracking-wide">1inme.com</span>
          <span className="tracking-[0.3em] uppercase">A guided tour</span>
        </div>
      </div>
    </div>
  );
}
