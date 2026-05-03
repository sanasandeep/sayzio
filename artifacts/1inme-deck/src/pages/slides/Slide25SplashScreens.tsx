export default function Slide25SplashScreens() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.18),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw]"><div className="flex items-center gap-[0.7vw]"><div className="h-[1.4vw] w-[1.4vw] rounded-md bg-gradient-to-br from-violet-500 to-fuchsia-500" /><span className="font-display text-[1.2vw] font-bold tracking-tight">1INME</span></div><span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">Splash screens</span></div>

      <div className="relative h-full w-full px-[7vw] pt-[12vh] pb-[8vh] grid grid-cols-12 gap-[3vw]">
        <div className="col-span-6 flex flex-col justify-center">
          <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight">A branded interstitial before the redirect.</h2>
          <p className="mt-[2.5vh] text-[1.4vw] text-slate-300 max-w-[36vw] leading-snug">Show your message, capture an email, prove the link is yours &mdash; then send the visitor on their way.</p>
          <div className="mt-[3vh] flex flex-col gap-[1vh] text-[1.2vw]">
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Pre-roll messages, password gates, age checks</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Optional email capture &amp; consent</span></div>
            <div className="flex items-center gap-[0.8vw]"><span className="text-fuchsia-300">&rarr;</span><span>Custom delay or skip-after-N-seconds</span></div>
          </div>
        </div>
        <div className="col-span-6 flex items-center justify-center">
          <div className="w-[28vw] aspect-[9/16] rounded-[3vw] border border-white/15 bg-gradient-to-br from-violet-500 to-fuchsia-500 p-[1.2vw] flex flex-col">
            <div className="flex-1 rounded-[2vw] bg-[#0a0a14] p-[1.4vw] flex flex-col">
              <div className="text-[0.9vw] uppercase tracking-[0.3em] text-fuchsia-300">Sponsored by</div>
              <div className="mt-[0.8vh] font-display text-[2vw] font-bold">Aurora Labs</div>
              <div className="mt-[3vh] font-display text-[2.2vw] font-semibold leading-tight">A new look at design systems &mdash; live this Friday.</div>
              <div className="mt-auto rounded-xl border border-white/10 bg-white/[0.05] p-[1.2vw] text-center text-[1vw] text-slate-300">Continue to original link in 5s</div>
            </div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500"><span>1inme.com</span><span>25 / 84</span></div>
    </div>
  );
}
