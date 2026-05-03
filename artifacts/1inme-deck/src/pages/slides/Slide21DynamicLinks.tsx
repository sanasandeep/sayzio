export default function Slide21DynamicLinks() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Dynamic links</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[4vw] font-bold leading-[1.02] tracking-tight max-w-[60vw]">One short link. Many destinations.</h2>
        <p className="mt-[2vh] text-[1.4vw] text-slate-300 max-w-[55vw]">Route visitors to the right place based on who they are and where they&rsquo;re coming from.</p>

        <div className="mt-[5vh] grid grid-cols-3 gap-[1.5vw]">
          <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-violet-300">Geo</div><div className="mt-[0.8vh] font-display text-[1.7vw] font-semibold">By country &amp; region</div><div className="mt-[0.8vh] text-[1.1vw] text-slate-400">US visitors land on /us, EU on /eu.</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-fuchsia-300">Device</div><div className="mt-[0.8vh] font-display text-[1.7vw] font-semibold">iOS · Android · desktop</div><div className="mt-[0.8vh] text-[1.1vw] text-slate-400">Send mobile users to the App Store.</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-cyan-300">Language</div><div className="mt-[0.8vh] font-display text-[1.7vw] font-semibold">By browser locale</div><div className="mt-[0.8vh] text-[1.1vw] text-slate-400">Serve native copy automatically.</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-violet-300">Time</div><div className="mt-[0.8vh] font-display text-[1.7vw] font-semibold">Schedules &amp; expiry</div><div className="mt-[0.8vh] text-[1.1vw] text-slate-400">Live windows, sunset URLs.</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-fuchsia-300">UTM</div><div className="mt-[0.8vh] font-display text-[1.7vw] font-semibold">Source-aware routing</div><div className="mt-[0.8vh] text-[1.1vw] text-slate-400">Different page per campaign.</div></div>
          <div className="rounded-xl border border-white/10 bg-white/[0.04] p-[1.6vw]"><div className="text-[1vw] uppercase tracking-[0.25em] text-cyan-300">A/B</div><div className="mt-[0.8vh] font-display text-[1.7vw] font-semibold">Weighted splits</div><div className="mt-[0.8vh] text-[1.1vw] text-slate-400">Traffic-share with stats.</div></div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>21 / 84</span></div>
    </div>
  );
}
