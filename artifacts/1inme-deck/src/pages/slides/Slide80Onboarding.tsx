export default function Slide80Onboarding() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.22),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Onboarding in 60 seconds</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">Live in four steps.</h2>

        <div className="mt-[5vh] grid grid-cols-4 gap-[1.5vw]">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="font-display text-[3vw] font-bold bg-gradient-to-br from-violet-300 to-fuchsia-300 bg-clip-text text-transparent leading-none">01</div><div className="mt-[1vh] font-display text-[1.5vw] font-semibold">Claim your handle</div><div className="text-[1.05vw] text-slate-300 mt-[0.6vh]">Pick 1inme.com/you and lock in your identity.</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="font-display text-[3vw] font-bold bg-gradient-to-br from-violet-300 to-fuchsia-300 bg-clip-text text-transparent leading-none">02</div><div className="mt-[1vh] font-display text-[1.5vw] font-semibold">Import your links</div><div className="text-[1.05vw] text-slate-300 mt-[0.6vh]">Pull from Linktree, Beacons, or Notion in one click.</div></div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.8vw]"><div className="font-display text-[3vw] font-bold bg-gradient-to-br from-violet-300 to-fuchsia-300 bg-clip-text text-transparent leading-none">03</div><div className="mt-[1vh] font-display text-[1.5vw] font-semibold">Pick a theme</div><div className="text-[1.05vw] text-slate-300 mt-[0.6vh]">Theme matches your brand colours automatically.</div></div>
          <div className="rounded-2xl border border-fuchsia-400/40 bg-fuchsia-500/10 p-[1.8vw]"><div className="font-display text-[3vw] font-bold text-fuchsia-200 leading-none">04</div><div className="mt-[1vh] font-display text-[1.5vw] font-semibold">Share your link</div><div className="text-[1.05vw] text-slate-300 mt-[0.6vh]">QR, NFC card, or share-sheet &mdash; anywhere you want.</div></div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>80 / 84</span></div>
    </div>
  );
}
