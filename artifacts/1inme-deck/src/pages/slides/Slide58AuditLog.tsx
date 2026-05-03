const base = import.meta.env.BASE_URL;

export default function Slide58AuditLog() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Audit log &amp; activity</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">Every change, recorded and searchable.</h2>
        <p className="mt-[1.5vh] text-[1.3vw] text-slate-300 max-w-[55vw]">Member events, content edits, vault access, billing changes &mdash; all in one log, exportable to your SIEM.</p>

        <div className="mt-[4vh] rounded-2xl border border-white/10 bg-white/[0.04] overflow-hidden">
          <div className="grid grid-cols-12 px-[1.5vw] py-[1.4vh] text-[0.95vw] text-slate-400 uppercase tracking-[0.2em] border-b border-white/10"><div className="col-span-2">When</div><div className="col-span-3">Actor</div><div className="col-span-3">Action</div><div className="col-span-2">Target</div><div className="col-span-2">IP</div></div>
          <div className="grid grid-cols-12 px-[1.5vw] py-[1.4vh] text-[1.05vw] border-b border-white/5 items-center"><div className="col-span-2 text-slate-400">14:02:41</div><div className="col-span-3">mira@aurora.co</div><div className="col-span-3 text-fuchsia-300">vault.read</div><div className="col-span-2 text-slate-300">stripe-live-key</div><div className="col-span-2 text-slate-400 font-mono">82.36.4.190</div></div>
          <div className="grid grid-cols-12 px-[1.5vw] py-[1.4vh] text-[1.05vw] border-b border-white/5 items-center"><div className="col-span-2 text-slate-400">13:58:12</div><div className="col-span-3">jonas@northwind.de</div><div className="col-span-3 text-violet-300">link.create</div><div className="col-span-2 text-slate-300">/launch</div><div className="col-span-2 text-slate-400 font-mono">88.12.55.7</div></div>
          <div className="grid grid-cols-12 px-[1.5vw] py-[1.4vh] text-[1.05vw] border-b border-white/5 items-center"><div className="col-span-2 text-slate-400">13:51:00</div><div className="col-span-3">aiko@studio.jp</div><div className="col-span-3 text-cyan-300">workspace.invite</div><div className="col-span-2 text-slate-300">+2 members</div><div className="col-span-2 text-slate-400 font-mono">219.117.4.2</div></div>
          <div className="grid grid-cols-12 px-[1.5vw] py-[1.4vh] text-[1.05vw] items-center"><div className="col-span-2 text-slate-400">13:44:38</div><div className="col-span-3">lucas@verde.br</div><div className="col-span-3 text-emerald-300">billing.update</div><div className="col-span-2 text-slate-300">Pro &rarr; Business</div><div className="col-span-2 text-slate-400 font-mono">177.41.220.3</div></div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>58 / 84</span></div>
    </div>
  );
}
