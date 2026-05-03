export default function Slide07Audience() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(34,211,238,0.12),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(124,58,237,0.2),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Audience</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[4vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">Built for everyone with a name worth a link.</h2>

        <div className="mt-[5vh] grid grid-cols-4 gap-[1.6vw]">
          <div className="rounded-2xl border border-violet-400/30 bg-gradient-to-br from-violet-500/15 to-transparent p-[2vw]">
            <div className="font-display text-[1.7vw] font-bold tracking-tight">Creators</div>
            <p className="mt-[1.2vh] text-[1.15vw] text-slate-300 leading-snug">Bio links, content, and monetisation in one place.</p>
          </div>
          <div className="rounded-2xl border border-fuchsia-400/30 bg-gradient-to-br from-fuchsia-500/15 to-transparent p-[2vw]">
            <div className="font-display text-[1.7vw] font-bold tracking-tight">Coaches &amp; Experts</div>
            <p className="mt-[1.2vh] text-[1.15vw] text-slate-300 leading-snug">Bookings, forms, and client follow-up handled.</p>
          </div>
          <div className="rounded-2xl border border-cyan-400/30 bg-gradient-to-br from-cyan-500/10 to-transparent p-[2vw]">
            <div className="font-display text-[1.7vw] font-bold tracking-tight">Sales &amp; Field</div>
            <p className="mt-[1.2vh] text-[1.15vw] text-slate-300 leading-snug">NFC tap, smart dialer, scanner, and CRM.</p>
          </div>
          <div className="rounded-2xl border border-violet-400/30 bg-gradient-to-br from-violet-500/15 to-transparent p-[2vw]">
            <div className="font-display text-[1.7vw] font-bold tracking-tight">Teams</div>
            <p className="mt-[1.2vh] text-[1.15vw] text-slate-300 leading-snug">Workspaces, vault, roles, and white-label.</p>
          </div>
        </div>

        <p className="mt-[5vh] text-[1.3vw] text-slate-400 max-w-[60vw]">From a solo creator to a 50-seat agency, 1INME scales with the role you play today.</p>
      </div>

      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>07 / 84</span></div>
    </div>
  );
}
