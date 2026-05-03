const base = import.meta.env.BASE_URL;

export default function Slide47Crm() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(236,72,153,0.16),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Contacts CRM</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">A CRM you don&rsquo;t have to feed.</h2>
        <p className="mt-[1.5vh] text-[1.3vw] text-slate-300 max-w-[55vw]">Contacts grow from every touchpoint &mdash; biolink visits, scans, forms, bookings, replies. Auto-deduped, auto-tagged.</p>

        <div className="mt-[4vh] rounded-2xl border border-white/10 bg-white/[0.04] overflow-hidden">
          <div className="grid grid-cols-12 px-[1.5vw] py-[1.4vh] text-[1vw] text-slate-400 uppercase tracking-[0.2em] border-b border-white/10"><div className="col-span-3">Name</div><div className="col-span-3">Email</div><div className="col-span-2">Source</div><div className="col-span-2">Tags</div><div className="col-span-2">Last touch</div></div>
          <div className="grid grid-cols-12 px-[1.5vw] py-[1.6vh] text-[1.1vw] border-b border-white/5 items-center"><div className="col-span-3 font-semibold">Mira Okafor</div><div className="col-span-3 text-slate-300">mira@aurora.co</div><div className="col-span-2 text-slate-300">Card scan</div><div className="col-span-2"><span className="px-[0.6vw] py-[0.2vh] text-[0.95vw] rounded bg-fuchsia-500/15 border border-fuchsia-400/30 text-fuchsia-200">partner</span></div><div className="col-span-2 text-slate-400">today</div></div>
          <div className="grid grid-cols-12 px-[1.5vw] py-[1.6vh] text-[1.1vw] border-b border-white/5 items-center"><div className="col-span-3 font-semibold">Jonas Weil</div><div className="col-span-3 text-slate-300">jonas@northwind.de</div><div className="col-span-2 text-slate-300">Form</div><div className="col-span-2"><span className="px-[0.6vw] py-[0.2vh] text-[0.95vw] rounded bg-violet-500/15 border border-violet-400/30 text-violet-200">lead</span></div><div className="col-span-2 text-slate-400">2d</div></div>
          <div className="grid grid-cols-12 px-[1.5vw] py-[1.6vh] text-[1.1vw] border-b border-white/5 items-center"><div className="col-span-3 font-semibold">Aiko Tanaka</div><div className="col-span-3 text-slate-300">aiko@studio.jp</div><div className="col-span-2 text-slate-300">Booking</div><div className="col-span-2"><span className="px-[0.6vw] py-[0.2vh] text-[0.95vw] rounded bg-cyan-500/15 border border-cyan-400/30 text-cyan-200">client</span></div><div className="col-span-2 text-slate-400">3d</div></div>
          <div className="grid grid-cols-12 px-[1.5vw] py-[1.6vh] text-[1.1vw] items-center"><div className="col-span-3 font-semibold">Lucas Andrade</div><div className="col-span-3 text-slate-300">lucas@verde.br</div><div className="col-span-2 text-slate-300">Biolink</div><div className="col-span-2"><span className="px-[0.6vw] py-[0.2vh] text-[0.95vw] rounded bg-emerald-500/15 border border-emerald-400/30 text-emerald-200">subscriber</span></div><div className="col-span-2 text-slate-400">5d</div></div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>47 / 84</span></div>
    </div>
  );
}
