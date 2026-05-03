const base = import.meta.env.BASE_URL;

export default function Slide35CardScanner() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(34,211,238,0.14),transparent_55%),radial-gradient(ellipse_at_bottom_right,rgba(236,72,153,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">AI · Card &amp; Brochure Scanner</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-7 flex flex-col justify-center">
          <span className="inline-block px-[1vw] py-[0.4vh] rounded-full bg-fuchsia-500/15 border border-fuchsia-400/40 text-[1vw] uppercase tracking-[0.25em] text-fuchsia-200 self-start">New</span>
          <h2 className="mt-[2vh] font-display text-[3.8vw] font-bold leading-[1.02] tracking-tight">Snap a business card. Get a contact.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[44vw] leading-snug">Scan business cards and brochures with your camera. We extract names, roles, emails, phone numbers, addresses, and links &mdash; then route them straight into the right CRM list.</p>
          <div className="mt-[3vh] flex flex-col gap-[1vh] text-[1.2vw]">
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Multi-card batch scanning</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Auto-tag, auto-list, auto-follow-up</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Brochure mode for menus &amp; flyers</span></div>
          </div>
        </div>
        <div className="col-span-5 flex items-center justify-center">
          <div className="w-full rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw]">
            <div className="rounded-xl bg-[#0d0d18] border border-white/10 p-[1.4vw]">
              <div className="text-[0.95vw] uppercase tracking-[0.25em] text-cyan-300">Extracted</div>
              <div className="mt-[1.2vh] font-display text-[1.8vw] font-semibold">Mira Okafor</div>
              <div className="text-[1.1vw] text-slate-400">Head of Partnerships, Aurora Labs</div>
              <div className="mt-[1.5vh] flex flex-col gap-[0.6vh] text-[1.05vw]">
                <div><span className="text-slate-500">email</span> mira@aurora.co</div>
                <div><span className="text-slate-500">phone</span> +1 (415) 555-0193</div>
                <div><span className="text-slate-500">site</span> aurora.co</div>
              </div>
              <div className="mt-[1.5vh] inline-block px-[0.8vw] py-[0.3vh] rounded-md bg-violet-500/15 border border-violet-400/30 text-[0.95vw] text-violet-200">Routed to: Partnerships list</div>
            </div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>35 / 84</span></div>
    </div>
  );
}
