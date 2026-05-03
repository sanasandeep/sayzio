const base = import.meta.env.BASE_URL;

export default function Slide13MobileApp() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <img src={`${base}hero-mobile.png`} crossOrigin="anonymous" alt="" className="absolute inset-0 w-full h-full object-cover opacity-50" />
      <div className="absolute inset-0 bg-[linear-gradient(110deg,rgba(10,10,20,0.95)_0%,rgba(10,10,20,0.7)_55%,rgba(10,10,20,0.35)_100%)]" />

      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><img src={`${base}logo-1inme-dark.png`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" /><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-300">Platforms · Mobile</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[14vh] pb-[8vh] flex flex-col">
        <span className="text-[1vw] uppercase tracking-[0.3em] text-fuchsia-300">Native iOS &amp; Android</span>
        <h2 className="mt-[1.5vh] font-display text-[4.4vw] font-bold leading-[1.02] tracking-tight max-w-[55vw]">Your identity in your pocket.</h2>
        <p className="mt-[2.5vh] text-[1.5vw] text-slate-200 max-w-[48vw] leading-snug">Built with Expo and React Native &mdash; the same product you know on the web, with native gestures, offline drafts, and push notifications.</p>

        <div className="mt-[5vh] grid grid-cols-3 gap-[1.5vw] max-w-[60vw]">
          <div className="rounded-xl border border-white/15 bg-white/[0.05] p-[1.4vw]"><div className="font-display text-[1.4vw] font-semibold">Native gestures</div><div className="mt-[0.6vh] text-[1.05vw] text-slate-300">Swipe, share-sheet, haptics.</div></div>
          <div className="rounded-xl border border-white/15 bg-white/[0.05] p-[1.4vw]"><div className="font-display text-[1.4vw] font-semibold">Offline drafts</div><div className="mt-[0.6vh] text-[1.05vw] text-slate-300">Compose anywhere, sync later.</div></div>
          <div className="rounded-xl border border-white/15 bg-white/[0.05] p-[1.4vw]"><div className="font-display text-[1.4vw] font-semibold">Push &amp; widgets</div><div className="mt-[0.6vh] text-[1.05vw] text-slate-300">Stay in the loop without the app open.</div></div>
        </div>
      </div>

      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-400"><span>1inme.com</span><span>13 / 84</span></div>
    </div>
  );
}
