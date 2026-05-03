const base = import.meta.env.BASE_URL;

export default function Slide10ByTheNumbers() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#11101c] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(236,72,153,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">By the numbers</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight max-w-[60vw]">A platform measured in surfaces, not features.</h2>

        <div className="mt-[6vh] grid grid-cols-4 gap-[2vw]">
          <div><div className="font-display text-[6vw] font-bold bg-gradient-to-br from-violet-300 to-fuchsia-300 bg-clip-text text-transparent leading-none">11</div><div className="mt-[1vh] text-[1.2vw] text-slate-300">Product modules</div></div>
          <div><div className="font-display text-[6vw] font-bold bg-gradient-to-br from-violet-300 to-fuchsia-300 bg-clip-text text-transparent leading-none">3</div><div className="mt-[1vh] text-[1.2vw] text-slate-300">Surfaces &mdash; web, mobile, API</div></div>
          <div><div className="font-display text-[6vw] font-bold bg-gradient-to-br from-violet-300 to-fuchsia-300 bg-clip-text text-transparent leading-none">50+</div><div className="mt-[1vh] text-[1.2vw] text-slate-300">Native integrations</div></div>
          <div><div className="font-display text-[6vw] font-bold bg-gradient-to-br from-violet-300 to-fuchsia-300 bg-clip-text text-transparent leading-none">1</div><div className="mt-[1vh] text-[1.2vw] text-slate-300">Identity that ties it all together</div></div>
        </div>

        <p className="mt-[6vh] text-[1.2vw] text-slate-400 max-w-[55vw]">Counts reflect modules shipped and surfaces actively maintained as of this deck&rsquo;s revision.</p>
      </div>

      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>10 / 84</span></div>
    </div>
  );
}
